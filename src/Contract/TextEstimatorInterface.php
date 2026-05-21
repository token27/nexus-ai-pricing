<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Contract;

/**
 * Minimal token-counting contract owned by the pricing library.
 *
 * Intentionally simpler than the full TokenizerInterface from nexus-ai-tokenizer:
 * it only returns the raw integer count needed for cost calculation, without
 * metadata, strategy details, or context-window helpers.
 *
 * ─── USAGE ────────────────────────────────────────────────────────────────────
 *
 *   // If you have token27/nexus-ai-tokenizer installed, use the bridge:
 *   use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;
 *   use Token27\Tokenizer\Registry\TokenizerRegistry;
 *
 *   $engine = new PricingEngine(
 *       textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
 *   );
 *
 *   // Or implement your own:
 *   class MyEstimator implements TextEstimatorInterface {
 *       public function estimateTokenCount(string $text, string $model): int {
 *           return (int) ceil(mb_strlen($text) / 4); // simple fallback
 *       }
 *       public function estimateChatTokenCount(array $messages, string $model): int {
 *           $total = 0;
 *           foreach ($messages as $m) {
 *               $total += (int) ceil(mb_strlen($m['content'] ?? '') / 4);
 *           }
 *           return $total + count($messages) * 3 + 3;
 *       }
 *   }
 *
 * ─── WHEN YOU DON'T NEED THIS ─────────────────────────────────────────────────
 *
 *   If you only use calculate() (post-request, passing token counts from the API),
 *   you do NOT need to configure a TextEstimatorInterface at all.
 *   PricingEngine works without it for that use case.
 */
interface TextEstimatorInterface
{
    /**
     * Count tokens in a plain text string for the given model.
     *
     * @param string $text  The text to count tokens for.
     * @param string $model The model identifier (e.g., 'gpt-4o', 'claude-sonnet-4-6').
     * @return int Estimated token count.
     */
    public function estimateTokenCount(string $text, string $model): int;

    /**
     * Count tokens in a full chat conversation for the given model.
     *
     * Should account for per-provider overhead (role tokens, ChatML framing, etc.)
     * when a real tokenizer is used.
     *
     * @param list<array{role?: string, content?: string}> $messages
     * @return int Estimated token count including overhead.
     */
    public function estimateChatTokenCount(array $messages, string $model): int;
}
