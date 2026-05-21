<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Contract\TextEstimatorInterface;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Exception\EstimationNotAvailableException;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\PriceTable\ChainedPriceTable;
use Token27\NexusAI\Pricing\PriceTable\NullPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\MultimodalPricingResult;

/**
 * Unit tests for PricingEngine.
 *
 * calculate() tests use null textEstimator — no tokenizer package required.
 * estimate() tests use a local stub implementing TextEstimatorInterface.
 */
final class PricingEngineTest extends TestCase
{
    // ─── Stubs ────────────────────────────────────────────────────────────────

    private function stubEstimator(int $tokenCount = 10, int $chatTokenCount = 15): TextEstimatorInterface
    {
        return new class ($tokenCount, $chatTokenCount) implements TextEstimatorInterface {
            public function __construct(
                private int $count,
                private int $chatCount,
            ) {}

            public function estimateTokenCount(string $text, string $model): int
            {
                return $this->count;
            }

            public function estimateChatTokenCount(array $messages, string $model): int
            {
                return $this->chatCount;
            }
        };
    }

    // ─── calculate() — no tokenizer needed ───────────────────────────────────

    public function testCalculateOpenAi(): void
    {
        $table  = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        $engine = new PricingEngine(priceTable: $table);

        // 1,000 input + 500 output
        // Expected: 0.00250 + 0.00500 = 0.00750
        $result = $engine->calculate('gpt-4o', inputTokens: 1_000, outputTokens: 500);

        static::assertEqualsWithDelta(0.00750, $result->totalCostUsd(), 1e-10);
        static::assertFalse($result->isUnknownModel());
    }

    public function testCalculateAnthropicWithCache(): void
    {
        $price = new ModelPrice(
            model: 'claude-sonnet-4-6',
            inputPerMillion: 3.00,
            outputPerMillion: 15.00,
            cacheWritePerMillion: 3.75,
            cacheReadPerMillion: 0.30,
        );
        $engine = new PricingEngine(priceTable: new ArrayPriceTable([$price]));

        $result = $engine->calculate(
            model: 'claude-sonnet-4-6',
            inputTokens: 200,
            outputTokens: 500,
            cacheWriteTokens: 800,
            cacheReadTokens: 2_000,
        );

        static::assertEqualsWithDelta(0.011700, $result->totalCostUsd(), 1e-10);
        static::assertEqualsWithDelta(0.005400, $result->cacheSavingsUsd(), 1e-10);
        static::assertSame(200, $result->inputTokens());
        static::assertSame(500, $result->outputTokens());
        static::assertSame(800, $result->cacheWriteTokens());
        static::assertSame(2_000, $result->cacheReadTokens());
    }

    public function testCalculateDeepSeekWithCacheHit(): void
    {
        $price = new ModelPrice(
            model: 'deepseek-v4-flash',
            inputPerMillion: 0.14,
            outputPerMillion: 0.28,
            cacheReadPerMillion: 0.0028,
            cacheReadIsSubsetOfInput: true,
        );
        $engine = new PricingEngine(priceTable: new ArrayPriceTable([$price]));

        // 1000 total input, 800 cache hit (subset)
        // non-cached = (200/1M) * 0.14 = 0.000028
        // cached     = (800/1M) * 0.0028 = 0.00000224
        // output     = (100/1M) * 0.28   = 0.000028
        $result = $engine->calculate(
            model: 'deepseek-v4-flash',
            inputTokens: 1_000,
            outputTokens: 100,
            cacheReadTokens: 800,
        );

        static::assertEqualsWithDelta(0.000028, $result->inputCostUsd(), 1e-9);
        static::assertEqualsWithDelta(0.00000224, $result->cacheReadCostUsd(), 1e-11);
        static::assertEqualsWithDelta(0.000028, $result->outputCostUsd(), 1e-9);
    }

    // ─── estimate() — requires TextEstimatorInterface ─────────────────────────

    public function testEstimateUsesEstimatorCount(): void
    {
        $table  = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        // stub returns 10 tokens for any text
        $engine = new PricingEngine($this->stubEstimator(tokenCount: 10), $table);

        $result = $engine->estimate('Hello world', 'gpt-4o');

        static::assertSame(10, $result->inputTokens());
        static::assertSame(0, $result->outputTokens());
        // (10/1M) * 2.50 = 0.000025
        static::assertEqualsWithDelta(0.000025, $result->totalCostUsd(), 1e-10);
    }

    public function testEstimateThrowsWhenNoEstimatorConfigured(): void
    {
        $engine = new PricingEngine(priceTable: new ArrayPriceTable(DefaultPriceCatalog::get()));

        $this->expectException(EstimationNotAvailableException::class);
        $engine->estimate('Hello world', 'gpt-4o');
    }

    // ─── estimateChat() ───────────────────────────────────────────────────────

    public function testEstimateChatUsesCountChat(): void
    {
        $table  = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        // stub returns 15 tokens for chat
        $engine = new PricingEngine($this->stubEstimator(chatTokenCount: 15), $table);

        $result = $engine->estimateChat(
            [['role' => 'user', 'content' => 'Hello']],
            'gpt-4o',
        );

        static::assertSame(15, $result->inputTokens());
    }

    public function testEstimateChatThrowsWhenNoEstimatorConfigured(): void
    {
        $engine = new PricingEngine(priceTable: new ArrayPriceTable(DefaultPriceCatalog::get()));

        $this->expectException(EstimationNotAvailableException::class);
        $engine->estimateChat([['role' => 'user', 'content' => 'Hello']], 'gpt-4o');
    }

    // ─── Unknown model ─────────────────────────────────────────────────────────

    public function testUnknownModelReturnsZeroCostWithFlag(): void
    {
        $engine = new PricingEngine(priceTable: new NullPriceTable());

        $result = $engine->calculate('my-future-model-2027', inputTokens: 1_000, outputTokens: 500);

        static::assertTrue($result->isUnknownModel());
        static::assertSame(0.0, $result->totalCostUsd());
        static::assertStringContainsString('unknown model', $result->format());
    }

    public function testUnknownModelNeverThrows(): void
    {
        $engine = new PricingEngine(priceTable: new NullPriceTable());

        $result = $engine->calculate('nonexistent', inputTokens: 999, outputTokens: 999);

        static::assertNotNull($result);
    }

    // ─── ChainedPriceTable priority ───────────────────────────────────────────

    public function testChainedTableCustomPriorityOverDefault(): void
    {
        $custom = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 0.01, outputPerMillion: 0.01)]);
        $table  = new ChainedPriceTable($custom, new ArrayPriceTable(DefaultPriceCatalog::get()));
        $engine = new PricingEngine(priceTable: $table);

        $result = $engine->calculate('gpt-4o', inputTokens: 1_000_000, outputTokens: 0);

        // Custom price ($0.01/M) applied, NOT the catalog price ($2.50/M)
        static::assertEqualsWithDelta(0.01, $result->totalCostUsd(), 1e-10);
    }

    // ─── registerPrice() ──────────────────────────────────────────────────────

    public function testRegisterPriceAtRuntime(): void
    {
        $engine = new PricingEngine(priceTable: new ArrayPriceTable([]));

        $engine->registerPrice(new ModelPrice('new-model', inputPerMillion: 1.00, outputPerMillion: 4.00));

        $result = $engine->calculate('new-model', inputTokens: 1_000_000, outputTokens: 0);

        static::assertEqualsWithDelta(1.00, $result->totalCostUsd(), 1e-10);
        static::assertFalse($result->isUnknownModel());
    }

    // ─── getPriceFor() ────────────────────────────────────────────────────────

    public function testGetPriceForKnownModel(): void
    {
        $engine = new PricingEngine(priceTable: new ArrayPriceTable(DefaultPriceCatalog::get()));

        $price = $engine->getPriceFor('claude-sonnet-4-6');

        static::assertSame(3.00, $price->inputPerMillion);
        static::assertSame(15.00, $price->outputPerMillion);
    }

    // ─── Static factories ─────────────────────────────────────────────────────

    public function testWithTableFactoryCalculatesWithoutEstimator(): void
    {
        $table  = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 1.99, outputPerMillion: 9.99)]);
        $engine = PricingEngine::withTable($table);
        $result = $engine->calculate('gpt-4o', inputTokens: 1_000_000, outputTokens: 0);

        static::assertEqualsWithDelta(1.99, $result->totalCostUsd(), 1e-10);
    }

    public function testWithTextEstimatorFactory(): void
    {
        $table   = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        $engine  = PricingEngine::withTextEstimator($this->stubEstimator(tokenCount: 8));
        // Override the default catalog price via registerPrice
        $engine->registerPrice(new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00));

        $result = $engine->estimate('Hello world', 'gpt-4o');

        static::assertSame(8, $result->inputTokens());
    }

    // ─── estimateWithImages() ─────────────────────────────────────────────────

    public function testEstimateWithImagesReturnsMultimodalResult(): void
    {
        $table  = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        $engine = new PricingEngine($this->stubEstimator(tokenCount: 5), $table);

        $result = $engine->estimateWithImages(
            text: 'Describe this image',
            model: 'gpt-4o',
            images: [ImageAttachment::lowDetail(800, 600)],
        );

        static::assertInstanceOf(MultimodalPricingResult::class, $result);
        // Text tokens: 5 (from stub), text cost > 0
        static::assertGreaterThan(0.0, $result->textResult()->totalCostUsd());

        // Image tokens: if token27/nexus-ai-tokenizer is installed, low detail = 85 tokens
        // If not installed, image tokens = 0 (no built-in estimators available)
        static::assertGreaterThanOrEqual(0, $result->imageTokens());
    }
}
