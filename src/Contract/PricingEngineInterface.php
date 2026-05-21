<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Contract;

use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\MultimodalPricingResult;

/**
 * Core contract for all pricing operations.
 *
 * Two families of methods:
 *   - estimate*()/estimateChat() — count tokens from text, then price them (pre-request)
 *   - calculate() — price already-known token counts from the LLM response (post-request)
 *
 * @example
 *   // Inject in your service container:
 *   $engine = new PricingEngine($tokenizer, $priceTable);
 *
 *   // Or use the zero-config static facade:
 *   $result = PricingEngine::for('gpt-4o')->calculate(inputTokens: 1200, outputTokens: 350);
 */
interface PricingEngineInterface
{
    /**
     * Estimate cost from a plain text string (pre-request).
     *
     * Internally counts tokens via the injected TokenizerInterface, then applies the model price.
     *
     * @param string $text  Text to tokenize and price.
     * @param string $model Model identifier.
     */
    public function estimate(string $text, string $model): PricingResultInterface;

    /**
     * Estimate cost from a full chat conversation, including provider overhead (pre-request).
     *
     * Accounts for ChatML / role tokens added by each provider.
     *
     * @param list<array{role?: string, content?: string}> $messages Ordered conversation.
     * @param string                                        $model   Model identifier.
     */
    public function estimateChat(array $messages, string $model): PricingResultInterface;

    /**
     * Calculate cost from real token counts returned by the LLM API (post-request).
     *
     * Supports Anthropic Prompt Caching (cacheWriteTokens / cacheReadTokens) and
     * OpenAI cached input tokens (pass as cacheReadTokens).
     *
     * For Anthropic:
     *   promptTokens    = usage.input_tokens             (non-cached, billed at input rate)
     *   cacheWriteTokens = usage.cache_creation_input_tokens (billed at cache-write rate)
     *   cacheReadTokens  = usage.cache_read_input_tokens  (billed at cache-read rate)
     *
     * For OpenAI cached input:
     *   promptTokens    = usage.prompt_tokens             (ALL input including cached)
     *   cacheReadTokens = usage.prompt_tokens_details.cached_tokens  (subset, billed at lower rate)
     *   → ModelPrice.cacheReadIsSubsetOfInput must be true for OpenAI models.
     *
     * @param string   $model            Model identifier.
     * @param int      $inputTokens      Standard (non-cached) input token count.
     * @param int      $outputTokens     Output token count.
     * @param int|null $cacheWriteTokens Cache-write tokens (Anthropic).
     * @param int|null $cacheReadTokens  Cache-read tokens (Anthropic or OpenAI cached subset).
     */
    public function calculate(
        string $model,
        int    $inputTokens,
        int    $outputTokens,
        ?int   $cacheWriteTokens = null,
        ?int   $cacheReadTokens  = null,
    ): PricingResultInterface;

    /**
     * Estimate cost including multimodal image tokens (pre-request).
     *
     * Text tokens use the injected TokenizerInterface.
     * Image tokens use the injected list of ImageTokenEstimatorInterface (OpenAI/Anthropic/Gemini
     * by default). Inject custom estimators via the PricingEngine constructor to support
     * proprietary models or override provider-specific formulas.
     *
     * @param string                $text   Text portion of the prompt.
     * @param string                $model  Model identifier.
     * @param list<ImageAttachment> $images Image descriptors.
     */
    public function estimateWithImages(
        string $text,
        string $model,
        array  $images,
    ): MultimodalPricingResult;

    /**
     * Register or overwrite the price of a model at runtime.
     *
     * Useful for private/enterprise models not in the built-in catalog.
     */
    public function registerPrice(ModelPrice $price): void;

    /**
     * Return the resolved ModelPrice for a model (for display in Prompt Studio UIs).
     *
     * Returns a zero-cost ModelPrice with isUnknownModel flag if not found.
     */
    public function getPriceFor(string $model): ModelPrice;
}
