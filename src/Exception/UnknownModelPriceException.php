<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Exception;

use RuntimeException;

/**
 * Thrown only when the caller explicitly requires a known price and none is found.
 *
 * NOTE: PricingEngine::calculate() and estimate() never throw this — they return
 * a PricingResult with isUnknownModel()=true instead. This exception is only for
 * contexts where a silent zero-cost result would be dangerous (e.g., a budget guard
 * that must reject unknown models rather than silently allowing free-seeming requests).
 */
final class UnknownModelPriceException extends RuntimeException
{
    public function __construct(string $model)
    {
        parent::__construct(
            "No price found for model '{$model}'. Register a ModelPrice via PricingEngine::registerPrice() or use a ChainedPriceTable with your custom prices.",
        );
    }
}
