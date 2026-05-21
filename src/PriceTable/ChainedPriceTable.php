<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\PriceTable;

use Token27\NexusAI\Pricing\Contract\PriceTableInterface;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

/**
 * Delegates to multiple price tables in priority order (first match wins).
 *
 * This is the recommended way to layer custom enterprise prices on top of
 * the built-in DefaultPriceCatalog without modifying the catalog itself.
 *
 * @example
 *   $table = new ChainedPriceTable(
 *       new JsonFilePriceTable('/etc/myapp/custom-prices.json'), // priority 1
 *       new ArrayPriceTable(DefaultPriceCatalog::get()),         // priority 2 (fallback)
 *   );
 *
 *   // Adding a third source dynamically:
 *   $table = $table->prepend(new JsonFilePriceTable('/etc/emergency-overrides.json'));
 */
final class ChainedPriceTable implements PriceTableInterface
{
    /** @var list<PriceTableInterface> */
    private array $tables;

    public function __construct(PriceTableInterface ...$tables)
    {
        $this->tables = array_values($tables);
    }

    public function getPrice(string $model): ModelPrice
    {
        foreach ($this->tables as $table) {
            if ($table->hasPrice($model)) {
                return $table->getPrice($model);
            }
        }

        return ModelPrice::zero($model);
    }

    public function hasPrice(string $model): bool
    {
        foreach ($this->tables as $table) {
            if ($table->hasPrice($model)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registers the price in the first (highest-priority) table.
     *
     * If the first table is read-only (e.g. JsonFilePriceTable), prices set
     * at runtime are stored in memory for this request only.
     */
    public function setPrice(ModelPrice $price): void
    {
        if ($this->tables !== []) {
            $this->tables[0]->setPrice($price);
        }
    }

    /** Returns all models from all tables (deduplicated, ordered by first occurrence). */
    public function getKnownModels(): array
    {
        $seen   = [];
        $result = [];

        foreach ($this->tables as $table) {
            foreach ($table->getKnownModels() as $model) {
                if (!isset($seen[$model])) {
                    $seen[$model] = true;
                    $result[]     = $model;
                }
            }
        }

        return $result;
    }

    /**
     * Return a new ChainedPriceTable with the given table added at the FRONT (highest priority).
     */
    public function prepend(PriceTableInterface $table): self
    {
        return new self($table, ...$this->tables);
    }

    /**
     * Return a new ChainedPriceTable with the given table added at the END (lowest priority).
     */
    public function append(PriceTableInterface $table): self
    {
        return new self(...[...$this->tables, $table]);
    }
}
