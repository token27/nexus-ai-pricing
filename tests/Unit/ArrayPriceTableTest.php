<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

final class ArrayPriceTableTest extends TestCase
{
    public function testExactMatch(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
        ]);

        $price = $table->getPrice('gpt-4o');

        static::assertSame('gpt-4o', $price->model);
        static::assertSame(2.50, $price->inputPerMillion);
    }

    public function testGlobPatternMatch(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('claude-opus-4*', inputPerMillion: 5.00, outputPerMillion: 25.00),
        ]);

        $price = $table->getPrice('claude-opus-4-7');

        // Glob pattern matched — model field is the concrete ID, not the pattern
        static::assertSame('claude-opus-4-7', $price->model);
        static::assertSame(5.00, $price->inputPerMillion);
    }

    public function testLongestPatternWins(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('claude-*', inputPerMillion: 1.00, outputPerMillion: 5.00),
            new ModelPrice('claude-sonnet-*', inputPerMillion: 3.00, outputPerMillion: 15.00),
        ]);

        $price = $table->getPrice('claude-sonnet-4-6');

        // 'claude-sonnet-*' is longer than 'claude-*', so it wins
        static::assertSame(3.00, $price->inputPerMillion);
    }

    public function testUnknownModelReturnsZero(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
        ]);

        $price = $table->getPrice('completely-unknown-model-2099');

        static::assertSame(0.0, $price->inputPerMillion);
        static::assertSame(0.0, $price->outputPerMillion);
        static::assertSame('completely-unknown-model-2099', $price->model);
    }

    public function testHasPriceExactMatch(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
        ]);

        static::assertTrue($table->hasPrice('gpt-4o'));
        static::assertFalse($table->hasPrice('gpt-5'));
    }

    public function testHasPriceGlobMatch(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('claude-*', inputPerMillion: 3.00, outputPerMillion: 15.00),
        ]);

        static::assertTrue($table->hasPrice('claude-sonnet-4-6'));
        static::assertFalse($table->hasPrice('gpt-4o'));
    }

    public function testSetPrice(): void
    {
        $table = new ArrayPriceTable([]);
        $table->setPrice(new ModelPrice('my-model', inputPerMillion: 1.00, outputPerMillion: 4.00));

        static::assertTrue($table->hasPrice('my-model'));
        static::assertSame(1.00, $table->getPrice('my-model')->inputPerMillion);
    }

    public function testSetPriceOverwritesExisting(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
        ]);
        $table->setPrice(new ModelPrice('gpt-4o', inputPerMillion: 1.99, outputPerMillion: 9.99));

        static::assertSame(1.99, $table->getPrice('gpt-4o')->inputPerMillion);
    }

    public function testGetKnownModels(): void
    {
        $table = new ArrayPriceTable([
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
            new ModelPrice('claude-*', inputPerMillion: 3.00, outputPerMillion: 15.00),
        ]);

        $known = $table->getKnownModels();

        static::assertCount(2, $known);
        static::assertContains('gpt-4o', $known);
        static::assertContains('claude-*', $known);
    }
}
