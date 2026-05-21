<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Exception;

use RuntimeException;

/**
 * Thrown when estimate() or estimateChat() is called on a PricingEngine
 * that has no TextEstimatorInterface configured.
 *
 * ─── SOLUTION ─────────────────────────────────────────────────────────────────
 *
 *   // Option A: use the built-in bridge if token27/nexus-ai-tokenizer is installed:
 *   use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;
 *   use Token27\Tokenizer\Registry\TokenizerRegistry;
 *
 *   $engine = new PricingEngine(
 *       textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
 *   );
 *
 *   // Option B: implement your own TextEstimatorInterface.
 *
 * ─── IF YOU ONLY NEED calculate() ─────────────────────────────────────────────
 *
 *   If you receive token counts from the LLM API response (the common case in
 *   post-request pipelines), you DON'T need estimate() at all.
 *   Use calculate() instead — it never calls the text estimator.
 */
final class EstimationNotAvailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'PricingEngine cannot estimate token counts: no TextEstimatorInterface is configured. ' .
            'Pass a TextEstimatorInterface to the constructor, or use calculate() if you already ' .
            'have token counts from the API response (no estimator needed for that).',
        );
    }
}
