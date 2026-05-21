<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Contract;

use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

/**
 * Pluggable source of per-model pricing data.
 *
 * Implementations may store prices in memory (ArrayPriceTable), on disk (JsonFilePriceTable),
 * or delegate through a chain of sources (ChainedPriceTable). All must be silent about unknown
 * models — getPrice() returns a zero-cost ModelPrice rather than throwing.
 *
 * @example
 *   // Array-backed (fastest, for hardcoded catalogs)
 *   $table = new ArrayPriceTable(DefaultPriceCatalog::get());
 *
 *   // File-backed (updatable without a deploy)
 *   $table = new JsonFilePriceTable('/etc/myapp/ai-prices.json');
 *
 *   // Chained (custom prices take priority over defaults)
 *   $table = new ChainedPriceTable(
 *       new JsonFilePriceTable('/etc/custom.json'),
 *       new ArrayPriceTable(DefaultPriceCatalog::get()),
 *   );
 */
interface PriceTableInterface
{
    /**
     * Return the price for the given model.
     *
     * Resolution order is implementation-defined (exact match, glob pattern, prefix, etc.).
     * If the model is not recognized, returns a ModelPrice with all costs set to 0.0.
     * Never throws for unknown models.
     *
     * @param string $model Model identifier, e.g. 'gpt-4o', 'claude-sonnet-4-6'.
     */
    public function getPrice(string $model): ModelPrice;

    /**
     * True when the table has an explicit price entry for the model.
     *
     * Unlike getPrice(), which always returns something, this distinguishes
     * "I know this model costs zero" from "I have never heard of this model".
     */
    public function hasPrice(string $model): bool;

    /**
     * Register or overwrite the price for a model.
     *
     * The $price->model field is used as the key. Implementations that support glob
     * patterns (like PricingRegistry) will match against it; others require exact IDs.
     */
    public function setPrice(ModelPrice $price): void;

    /**
     * Return every model identifier with an explicit price entry.
     *
     * Does NOT expand glob patterns — returns the patterns themselves.
     *
     * @return list<string>
     */
    public function getKnownModels(): array;
}
