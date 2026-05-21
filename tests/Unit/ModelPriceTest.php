<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

final class ModelPriceTest extends TestCase
{
    public function testBasicPrice(): void
    {
        $price = new ModelPrice(
            model: 'gpt-4o',
            inputPerMillion: 2.50,
            outputPerMillion: 10.00,
        );

        static::assertSame('gpt-4o', $price->model);
        static::assertSame(2.50, $price->inputPerMillion);
        static::assertSame(10.00, $price->outputPerMillion);
        static::assertNull($price->cacheWritePerMillion);
        static::assertNull($price->cacheReadPerMillion);
        static::assertFalse($price->supportsCache());
        static::assertFalse($price->hasWriteSurcharge());
        static::assertFalse($price->supportsVision());
    }

    public function testAnthropicCachePrice(): void
    {
        $price = new ModelPrice(
            model: 'claude-sonnet-4-6',
            inputPerMillion: 3.00,
            outputPerMillion: 15.00,
            cacheWritePerMillion: 3.75,
            cacheReadPerMillion: 0.30,
            cacheReadIsSubsetOfInput: false,
        );

        static::assertTrue($price->supportsCache());
        static::assertTrue($price->hasWriteSurcharge());
        static::assertFalse($price->cacheReadIsSubsetOfInput);
        static::assertSame(3.75, $price->cacheWritePerMillion);
        static::assertSame(0.30, $price->cacheReadPerMillion);
    }

    public function testOpenAICachePrice(): void
    {
        $price = new ModelPrice(
            model: 'gpt-4o',
            inputPerMillion: 2.50,
            outputPerMillion: 10.00,
            cacheReadPerMillion: 1.25,
            cacheReadIsSubsetOfInput: true,
        );

        static::assertTrue($price->supportsCache());
        static::assertFalse($price->hasWriteSurcharge());
        static::assertTrue($price->cacheReadIsSubsetOfInput);
        static::assertNull($price->cacheWritePerMillion);
    }

    public function testVisionPrice(): void
    {
        $price = new ModelPrice(
            model: 'gpt-4o',
            inputPerMillion: 2.50,
            outputPerMillion: 10.00,
            imageInputPerMillion: 2.50,
        );

        static::assertTrue($price->supportsVision());
        static::assertSame(2.50, $price->effectiveImagePrice());
    }

    public function testEffectiveImagePriceFallsBackToInput(): void
    {
        $price = new ModelPrice(
            model: 'gpt-4o',
            inputPerMillion: 2.50,
            outputPerMillion: 10.00,
        );

        // No imageInputPerMillion set — falls back to inputPerMillion
        static::assertSame(2.50, $price->effectiveImagePrice());
    }

    public function testZeroSentinel(): void
    {
        $price = ModelPrice::zero('unknown-model');

        static::assertSame('unknown-model', $price->model);
        static::assertSame(0.0, $price->inputPerMillion);
        static::assertSame(0.0, $price->outputPerMillion);
        static::assertFalse($price->supportsCache());
    }

    public function testWithModel(): void
    {
        $price    = new ModelPrice('claude-opus-4*', inputPerMillion: 5.00, outputPerMillion: 25.00);
        $concrete = $price->withModel('claude-opus-4-7');

        static::assertSame('claude-opus-4-7', $concrete->model);
        static::assertSame(5.00, $concrete->inputPerMillion);
        static::assertSame(25.00, $concrete->outputPerMillion);
    }
}
