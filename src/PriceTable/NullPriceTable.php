<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\PriceTable;

use Token27\NexusAI\Pricing\Contract\PriceTableInterface;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

/**
 * A price table that always returns zero costs.
 *
 * Useful in tests where you want to verify token counting without caring about
 * the monetary result, or as a sentinel when all costs should be suppressed.
 *
 * @example
 *   $engine = new PricingEngine($tokenizer, new NullPriceTable());
 *   $result = $engine->estimate('Hello world', 'any-model');
 *   assert($result->totalCostUsd() === 0.0);
 *   assert($result->isUnknownModel() === true);
 */
final class NullPriceTable implements PriceTableInterface
{
    public function getPrice(string $model): ModelPrice
    {
        return ModelPrice::zero($model);
    }

    public function hasPrice(string $model): bool
    {
        return false;
    }

    public function setPrice(ModelPrice $price): void
    {
        // intentionally no-op
    }

    public function getKnownModels(): array
    {
        return [];
    }
}
