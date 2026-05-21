<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\PricingResult;

final class PricingResultTest extends TestCase
{
    // ─── Basic calculation ─────────────────────────────────────────────────────

    public function testGptFourOCalculation(): void
    {
        // gpt-4o: $2.50/M input, $10.00/M output
        // 1,000 input + 500 output
        // Expected: (1000/1M)*2.50 + (500/1M)*10.00 = 0.00250 + 0.00500 = 0.00750
        $price  = new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00);
        $result = PricingResult::compute($price, inputTokens: 1_000, outputTokens: 500);

        static::assertEqualsWithDelta(0.00250, $result->inputCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.00500, $result->outputCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.00750, $result->totalCostUsd(), 1e-10);
        static::assertSame(1_000, $result->inputTokens());
        static::assertSame(500, $result->outputTokens());
        static::assertFalse($result->isUnknownModel());
        static::assertFalse($result->isZero());
    }

    public function testClaudeSonnetWithAnthropicCache(): void
    {
        // claude-sonnet-4-6: $3.00/M input, $15.00/M output, $3.75/M cacheWrite, $0.30/M cacheRead
        // 200 standard input + 500 output + 800 cacheWrite + 2000 cacheRead
        //
        // inputCost    = (200/1M) * 3.00   = 0.000600
        // outputCost   = (500/1M) * 15.00  = 0.007500
        // cacheWrite   = (800/1M) * 3.75   = 0.003000
        // cacheRead    = (2000/1M) * 0.30  = 0.000600
        // total        = 0.011700
        // savings      = (2000/1M) * (3.00 - 0.30) = (2000/1M) * 2.70 = 0.005400
        $price = new ModelPrice(
            model: 'claude-sonnet-4-6',
            inputPerMillion: 3.00,
            outputPerMillion: 15.00,
            cacheWritePerMillion: 3.75,
            cacheReadPerMillion: 0.30,
            cacheReadIsSubsetOfInput: false,
        );
        $result = PricingResult::compute(
            price: $price,
            inputTokens: 200,
            outputTokens: 500,
            cacheWriteTokens: 800,
            cacheReadTokens: 2_000,
        );

        static::assertEqualsWithDelta(0.000600, $result->inputCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.007500, $result->outputCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.003000, $result->cacheWriteCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.000600, $result->cacheReadCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.011700, $result->totalCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.005400, $result->cacheSavingsUsd(), 1e-10);
    }

    public function testOpenAICachedInputSubset(): void
    {
        // gpt-4o: $2.50/M input, $10.00/M output, $1.25/M cached (subset)
        // 1000 total input, 600 of which were cached
        //
        // Non-cached part = (1000 - 600) / 1M * 2.50 = 400/1M * 2.50 = 0.001000
        // Cached part     = 600/1M * 1.25             = 0.000750
        // output          = 200/1M * 10.00             = 0.002000
        // total           = 0.003750
        // savings         = 600/1M * (2.50 - 1.25)   = 600/1M * 1.25 = 0.000750
        $price = new ModelPrice(
            model: 'gpt-4o',
            inputPerMillion: 2.50,
            outputPerMillion: 10.00,
            cacheReadPerMillion: 1.25,
            cacheReadIsSubsetOfInput: true,
        );
        $result = PricingResult::compute(
            price: $price,
            inputTokens: 1_000,
            outputTokens: 200,
            cacheReadTokens: 600,
        );

        // inputCostUsd reflects the non-cached portion (400 tokens)
        static::assertEqualsWithDelta(0.001000, $result->inputCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.000750, $result->cacheReadCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.002000, $result->outputCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.003750, $result->totalCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.000750, $result->cacheSavingsUsd(), 1e-10);
    }

    public function testUnknownModelFlag(): void
    {
        $result = PricingResult::unknown('my-new-model-2027');

        static::assertTrue($result->isUnknownModel());
        static::assertTrue($result->isZero());
        static::assertSame(0.0, $result->totalCostUsd());
        static::assertStringContainsString('unknown model', $result->format());
    }

    // ─── add() accumulation ────────────────────────────────────────────────────

    public function testAddCombinesTwoResults(): void
    {
        $price = new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00);
        $r1    = PricingResult::compute($price, inputTokens: 1_000, outputTokens: 500);
        $r2    = PricingResult::compute($price, inputTokens: 500, outputTokens: 200);
        $total = $r1->add($r2);

        static::assertSame(1_500, $total->inputTokens());
        static::assertSame(700, $total->outputTokens());
        static::assertEqualsWithDelta(
            $r1->totalCostUsd() + $r2->totalCostUsd(),
            $total->totalCostUsd(),
            1e-10,
        );
    }

    public function testAddIsImmutable(): void
    {
        $price  = new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00);
        $r1     = PricingResult::compute($price, inputTokens: 1_000, outputTokens: 500);
        $r2     = PricingResult::compute($price, inputTokens: 500, outputTokens: 200);
        $before = $r1->totalCostUsd();

        $r1->add($r2);

        static::assertEqualsWithDelta($before, $r1->totalCostUsd(), 1e-10, 'add() must not mutate the original');
    }

    // ─── format() ─────────────────────────────────────────────────────────────

    public function testFormat(): void
    {
        $price  = new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00);
        $result = PricingResult::compute($price, inputTokens: 1_000, outputTokens: 500);

        static::assertStringContainsString('USD', $result->format());
        static::assertStringContainsString('1,000 input', $result->format());
        static::assertStringContainsString('500 output', $result->format());
    }

    // ─── toArray() ────────────────────────────────────────────────────────────

    public function testToArray(): void
    {
        $price  = new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00);
        $result = PricingResult::compute($price, inputTokens: 1_000, outputTokens: 500);
        $arr    = $result->toArray();

        static::assertArrayHasKey('model', $arr);
        static::assertArrayHasKey('total_cost_usd', $arr);
        static::assertArrayHasKey('input_tokens', $arr);
        static::assertArrayHasKey('output_tokens', $arr);
        static::assertArrayHasKey('is_unknown_model', $arr);
        static::assertSame('gpt-4o', $arr['model']);
        static::assertSame(1_000, $arr['input_tokens']);
    }

    // ─── Catalog sanity ───────────────────────────────────────────────────────

    public function testDefaultCatalogHasNonZeroPrices(): void
    {
        $catalog = \Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog::get();

        static::assertNotEmpty($catalog, 'DefaultPriceCatalog must not be empty');

        foreach ($catalog as $price) {
            static::assertInstanceOf(ModelPrice::class, $price);
            // Prices must be documented (non-zero) — zero means "forgot to fill in"
            static::assertGreaterThan(
                0.0,
                $price->inputPerMillion,
                "Model '{$price->model}' has zero inputPerMillion — update DefaultPriceCatalog!",
            );
            static::assertGreaterThan(
                0.0,
                $price->outputPerMillion,
                "Model '{$price->model}' has zero outputPerMillion — update DefaultPriceCatalog!",
            );
        }
    }
}
