<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Tests\Unit;

use function dirname;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Pricing\Exception\PriceTableException;
use Token27\NexusAI\Pricing\PriceTable\JsonFilePriceTable;

final class JsonFilePriceTableTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__) . '/fixtures';
    }

    public function testLoadsFromJsonFile(): void
    {
        $table = new JsonFilePriceTable($this->fixturesDir . '/custom-prices.json');

        static::assertTrue($table->hasPrice('my-private-llm'));
        $price = $table->getPrice('my-private-llm');

        static::assertSame('my-private-llm', $price->model);
        static::assertSame(1.50, $price->inputPerMillion);
        static::assertSame(5.00, $price->outputPerMillion);
        static::assertSame(1.875, $price->cacheWritePerMillion);
        static::assertSame(0.15, $price->cacheReadPerMillion);
        static::assertFalse($price->cacheReadIsSubsetOfInput);
    }

    public function testLoadsSecondEntry(): void
    {
        $table = new JsonFilePriceTable($this->fixturesDir . '/custom-prices.json');
        $price = $table->getPrice('my-fast-llm');

        static::assertSame(0.10, $price->inputPerMillion);
        static::assertSame(0.30, $price->outputPerMillion);
        static::assertNull($price->cacheWritePerMillion);
    }

    public function testGlobPatternInJsonFile(): void
    {
        $table = new JsonFilePriceTable($this->fixturesDir . '/custom-prices.json');

        // 'enterprise-model-*' pattern from the fixture should match
        static::assertTrue($table->hasPrice('enterprise-model-v1'));
        $price = $table->getPrice('enterprise-model-v1');

        static::assertSame(2.00, $price->inputPerMillion);
        static::assertTrue($price->cacheReadIsSubsetOfInput);
    }

    public function testLazyLoadingOnFirstAccess(): void
    {
        // File is not read until first getPrice/hasPrice call
        $table = new JsonFilePriceTable($this->fixturesDir . '/custom-prices.json');

        // No exception yet (lazy)
        $price = $table->getPrice('my-private-llm');
        static::assertSame(1.50, $price->inputPerMillion);
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(PriceTableException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $table = new JsonFilePriceTable('/nonexistent/path/prices.json');
        $table->getPrice('any-model'); // triggers lazy load
    }

    public function testGetKnownModelsFromFile(): void
    {
        $table = new JsonFilePriceTable($this->fixturesDir . '/custom-prices.json');
        $known = $table->getKnownModels();

        static::assertContains('my-private-llm', $known);
        static::assertContains('my-fast-llm', $known);
    }
}
