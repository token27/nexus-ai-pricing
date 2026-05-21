<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Adapter;

use Token27\NexusAI\Pricing\Contract\TextEstimatorInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;

/**
 * Bridges token27/nexus-ai-tokenizer's TokenizerInterface to PricingEngine's TextEstimatorInterface.
 *
 * IMPORTANT: Only instantiate this class when token27/nexus-ai-tokenizer is installed.
 * This file is safe to include in the package (PHP won't autoload TokenizerInterface
 * unless this class is actually instantiated), but calling `new TokenizerBridgeAdapter(...)`
 * requires the tokenizer package to be present.
 *
 * ─── USAGE ────────────────────────────────────────────────────────────────────
 *
 *   use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;
 *   use Token27\NexusAI\Pricing\Engine\PricingEngine;
 *   use Token27\Tokenizer\Registry\TokenizerRegistry;
 *
 *   // Zero-config bridge (uses all built-in strategies + CharDivision fallback):
 *   $engine = new PricingEngine(
 *       textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
 *   );
 *
 *   // Or with a specific custom strategy:
 *   $engine = new PricingEngine(
 *       textEstimator: new TokenizerBridgeAdapter(
 *           TokenizerEngine::withHuggingFaceJson('/path/tokenizer.json', 'deepseek-*')
 *               ->make('deepseek-v4-flash')  // returns a builder, not a TokenizerInterface...
 *       ),
 *       // ... use the registry directly instead:
 *       textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
 *   );
 *
 * @see TextEstimatorInterface
 */
final class TokenizerBridgeAdapter implements TextEstimatorInterface
{
    public function __construct(
        private readonly TokenizerInterface $tokenizer,
    ) {}

    public function estimateTokenCount(string $text, string $model): int
    {
        return $this->tokenizer->count($text, $model)->count();
    }

    /**
     * @param list<array{role?: string, content?: string}> $messages
     */
    public function estimateChatTokenCount(array $messages, string $model): int
    {
        return $this->tokenizer->countChat($messages, $model)->count();
    }
}
