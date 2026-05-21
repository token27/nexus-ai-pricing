<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\PriceTable;

use function strlen;

use Token27\NexusAI\Pricing\Contract\PriceTableInterface;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

/**
 * In-memory price table backed by an array of ModelPrice instances.
 *
 * Lookup resolution order:
 *   1. Exact model ID match
 *   2. Glob pattern match (fnmatch); longest pattern wins on ties
 *   3. Returns ModelPrice::zero() if nothing matches
 *
 * This is the fastest PriceTableInterface implementation and the default
 * used by ArrayPriceTable(DefaultPriceCatalog::get()).
 *
 * @example
 *   $table = new ArrayPriceTable(DefaultPriceCatalog::get());
 *   $price = $table->getPrice('claude-sonnet-4-6');
 *   echo $price->inputPerMillion; // 3.00
 *
 *   // Register a glob pattern covering all future claude-opus-5 variants
 *   $table->setPrice(new ModelPrice('claude-opus-5*', inputPerMillion: 8.00, outputPerMillion: 40.00));
 */
final class ArrayPriceTable implements PriceTableInterface
{
    /** @var array<string, ModelPrice> key = model/pattern */
    private array $prices = [];

    /**
     * @param list<ModelPrice> $prices Initial prices. Supports exact IDs and glob patterns.
     */
    public function __construct(array $prices = [])
    {
        foreach ($prices as $price) {
            $this->prices[$price->model] = $price;
        }
    }

    public function getPrice(string $model): ModelPrice
    {
        // 1. Exact match
        if (isset($this->prices[$model])) {
            return $this->prices[$model];
        }

        // 2. Glob match — longest pattern wins (most specific)
        $best     = null;
        $bestLen  = -1;

        foreach ($this->prices as $pattern => $price) {
            if ($pattern === $model) {
                continue; // already handled above
            }

            if (fnmatch($pattern, $model) && strlen($pattern) > $bestLen) {
                $best    = $price;
                $bestLen = strlen($pattern);
            }
        }

        if ($best !== null) {
            // Return with the concrete model ID so callers see the actual model, not the pattern
            return $best->withModel($model);
        }

        return ModelPrice::zero($model);
    }

    public function hasPrice(string $model): bool
    {
        if (isset($this->prices[$model])) {
            return true;
        }

        foreach (array_keys($this->prices) as $pattern) {
            if ($pattern !== $model && fnmatch($pattern, $model)) {
                return true;
            }
        }

        return false;
    }

    public function setPrice(ModelPrice $price): void
    {
        $this->prices[$price->model] = $price;
    }

    public function getKnownModels(): array
    {
        return array_keys($this->prices);
    }
}
