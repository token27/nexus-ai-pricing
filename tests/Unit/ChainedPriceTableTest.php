<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Tests\Unit;

use function count;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\PriceTable\ChainedPriceTable;
use Token27\NexusAI\Pricing\PriceTable\NullPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

final class ChainedPriceTableTest extends TestCase
{
    public function testFirstTableTakesPriority(): void
    {
        $custom   = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 1.00, outputPerMillion: 4.00)]);
        $defaults = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        $chained  = new ChainedPriceTable($custom, $defaults);

        // custom has higher priority
        static::assertSame(1.00, $chained->getPrice('gpt-4o')->inputPerMillion);
    }

    public function testFallsBackToSecondTable(): void
    {
        $custom   = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 1.00, outputPerMillion: 4.00)]);
        $defaults = new ArrayPriceTable([new ModelPrice('claude-sonnet-4-6', inputPerMillion: 3.00, outputPerMillion: 15.00)]);
        $chained  = new ChainedPriceTable($custom, $defaults);

        // gpt-4o is in custom, claude is in defaults
        static::assertSame(1.00, $chained->getPrice('gpt-4o')->inputPerMillion);
        static::assertSame(3.00, $chained->getPrice('claude-sonnet-4-6')->inputPerMillion);
    }

    public function testUnknownModelReturnsZero(): void
    {
        $chained = new ChainedPriceTable(
            new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]),
            new NullPriceTable(),
        );

        $price = $chained->getPrice('totally-unknown-model-xyz');

        static::assertSame(0.0, $price->inputPerMillion);
    }

    public function testHasPriceChecksAllTables(): void
    {
        $t1      = new ArrayPriceTable([new ModelPrice('model-a', inputPerMillion: 1.0, outputPerMillion: 2.0)]);
        $t2      = new ArrayPriceTable([new ModelPrice('model-b', inputPerMillion: 1.0, outputPerMillion: 2.0)]);
        $chained = new ChainedPriceTable($t1, $t2);

        static::assertTrue($chained->hasPrice('model-a'));
        static::assertTrue($chained->hasPrice('model-b'));
        static::assertFalse($chained->hasPrice('model-c'));
    }

    public function testSetPriceGoesToFirstTable(): void
    {
        $t1      = new ArrayPriceTable([]);
        $t2      = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        $chained = new ChainedPriceTable($t1, $t2);

        $chained->setPrice(new ModelPrice('gpt-4o', inputPerMillion: 1.00, outputPerMillion: 4.00));

        // t1 now has gpt-4o at lower price — takes priority
        static::assertSame(1.00, $chained->getPrice('gpt-4o')->inputPerMillion);
    }

    public function testGetKnownModelsDeduplicates(): void
    {
        $t1      = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]);
        $t2      = new ArrayPriceTable([
            new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00),
            new ModelPrice('claude-*', inputPerMillion: 3.00, outputPerMillion: 15.00),
        ]);
        $chained = new ChainedPriceTable($t1, $t2);
        $known   = $chained->getKnownModels();

        // gpt-4o appears in both but should only be listed once
        static::assertSame(count($known), count(array_unique($known)));
        static::assertContains('gpt-4o', $known);
        static::assertContains('claude-*', $known);
    }

    public function testPrependAddsHighestPriority(): void
    {
        $base      = new ChainedPriceTable(
            new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 2.50, outputPerMillion: 10.00)]),
        );
        $emergency = new ArrayPriceTable([new ModelPrice('gpt-4o', inputPerMillion: 0.01, outputPerMillion: 0.01)]);
        $chained   = $base->prepend($emergency);

        static::assertSame(0.01, $chained->getPrice('gpt-4o')->inputPerMillion);
        // Original not mutated
        static::assertSame(2.50, $base->getPrice('gpt-4o')->inputPerMillion);
    }
}
