<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Builder;

use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;
use Token27\NexusAI\Pricing\ValueObject\MultimodalPricingResult;

/**
 * Fluent interface for pricing operations against a specific model.
 *
 * Created by PricingEngine::for($model) — do not instantiate directly.
 * All methods are terminal: they return a pricing result immediately.
 *
 * @example
 *   // Zero-config, one-liner:
 *   $result = PricingEngine::for('gpt-4o')->calculate(inputTokens: 1200, outputTokens: 350);
 *
 *   // Estimate before sending (counts tokens internally):
 *   $result = PricingEngine::for('claude-sonnet-4-6')->estimate('Write a PHP function');
 *
 *   // With Anthropic prompt caching:
 *   $result = PricingEngine::for('claude-sonnet-4-6')->calculate(
 *       inputTokens:      1_000,
 *       outputTokens:       500,
 *       cacheWriteTokens:   800,
 *       cacheReadTokens:    200,
 *   );
 *   echo $result->cacheSavingsUsd(); // real savings vs standard rate
 *
 *   // With images:
 *   $result = PricingEngine::for('gpt-4o')->estimateWithImages(
 *       text:   'Describe this chart.',
 *       images: [ImageAttachment::highDetail(1920, 1080)],
 *   );
 */
final class PricingBuilder
{
    public function __construct(
        private readonly string                 $model,
        private readonly PricingEngineInterface $engine,
    ) {}

    /**
     * Estimate cost from a plain text string (pre-request).
     *
     * Counts tokens internally via the engine's TokenizerInterface.
     */
    public function estimate(string $text): PricingResultInterface
    {
        return $this->engine->estimate($text, $this->model);
    }

    /**
     * Estimate cost from a full chat conversation (pre-request, includes provider overhead).
     *
     * @param list<array{role?: string, content?: string}> $messages
     */
    public function estimateChat(array $messages): PricingResultInterface
    {
        return $this->engine->estimateChat($messages, $this->model);
    }

    /**
     * Calculate cost from real token counts returned by the LLM API (post-request).
     *
     * @param int      $inputTokens      Standard input tokens (non-cached for Anthropic).
     * @param int      $outputTokens     Output tokens.
     * @param int|null $cacheWriteTokens Tokens written to cache (Anthropic prompt caching).
     * @param int|null $cacheReadTokens  Tokens read from cache (Anthropic) or cached subset (OpenAI).
     */
    public function calculate(
        int  $inputTokens,
        int  $outputTokens,
        ?int $cacheWriteTokens = null,
        ?int $cacheReadTokens  = null,
    ): PricingResultInterface {
        return $this->engine->calculate(
            $this->model,
            $inputTokens,
            $outputTokens,
            $cacheWriteTokens,
            $cacheReadTokens,
        );
    }

    /**
     * Estimate cost for a multimodal prompt with images (pre-request).
     *
     * @param list<ImageAttachment> $images
     */
    public function estimateWithImages(string $text, array $images): MultimodalPricingResult
    {
        return $this->engine->estimateWithImages($text, $this->model, $images);
    }

    /** Return the model this builder is bound to. */
    public function getModel(): string
    {
        return $this->model;
    }

    /** Return the resolved ModelPrice for this model (for display in UIs). */
    public function getPrice(): \Token27\NexusAI\Pricing\ValueObject\ModelPrice
    {
        return $this->engine->getPriceFor($this->model);
    }
}
