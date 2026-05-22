<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\ValueObject;

/**
 * Immutable price descriptor for a single LLM model.
 *
 * All monetary values are USD per million tokens. Fields that are null mean
 * "this feature is not supported or not applicable for this model".
 *
 * Caching architectures differ by provider:
 *
 *   ANTHROPIC (additive):
 *     - cacheWritePerMillion: surcharge for creating a cache block (1.25× input price)
 *     - cacheReadPerMillion:  discount for reading from cache (0.10× input price)
 *     - cacheReadIsSubsetOfInput = false (cache tokens are ADDITIONAL to promptTokens)
 *
 *   OPENAI / GROQ (subset):
 *     - cacheWritePerMillion: null (no write surcharge — caching is automatic)
 *     - cacheReadPerMillion:  discounted rate for cached prefix tokens
 *     - cacheReadIsSubsetOfInput = true (cached_tokens is a subset of prompt_tokens)
 *
 * @example
 *   // GPT-4o with OpenAI automatic caching
 *   new ModelPrice(
 *       model: 'gpt-4o',
 *       inputPerMillion: 2.50,
 *       outputPerMillion: 10.00,
 *       cacheReadPerMillion: 1.25,
 *       cacheReadIsSubsetOfInput: true,
 *   );
 *
 *   // Claude Sonnet 4.6 with Anthropic Prompt Caching
 *   new ModelPrice(
 *       model: 'claude-sonnet-4-6',
 *       inputPerMillion: 3.00,
 *       outputPerMillion: 15.00,
 *       cacheWritePerMillion: 3.75,
 *       cacheReadPerMillion: 0.30,
 *       cacheReadIsSubsetOfInput: false,
 *   );
 */
final readonly class ModelPrice
{
    public function __construct(
        /** Model identifier (exact ID or glob pattern like 'claude-opus-4*'). */
        public string  $model,

        /** Standard input token price in USD per million tokens. */
        public float   $inputPerMillion,

        /** Standard output token price in USD per million tokens. */
        public float   $outputPerMillion,

        /**
         * Price for creating (writing) a cache block, USD per million tokens.
         * Anthropic only. Null for providers without a write surcharge.
         */
        public ?float  $cacheWritePerMillion = null,

        /**
         * Price for reading (hitting) cached tokens, USD per million tokens.
         * Set for both Anthropic (0.10× input) and OpenAI (0.50× input for automatic caching).
         */
        public ?float  $cacheReadPerMillion = null,

        /**
         * When true, cache-read tokens are a SUBSET of inputTokens already counted.
         * Use true for OpenAI (cached_tokens ⊂ prompt_tokens).
         * Use false (default) for Anthropic (cache tokens are additive).
         */
        public bool    $cacheReadIsSubsetOfInput = false,

        /**
         * Price per million image-equivalent tokens for multimodal/vision requests.
         * When null, image tokens are billed at the standard inputPerMillion rate.
         */
        public ?float  $imageInputPerMillion = null,
        public ?float  $imageOutputPerMillion = null,
        public ?float  $perImageCost = null,

        /** ISO 4217 currency code. Always 'USD' in the built-in catalog. */
        public string  $currency = 'USD',

        /**
         * Human-readable provenance note — include source URL and verification date.
         *
         * @example "Source: platform.claude.com/docs/en/about-claude/pricing | Verified: 2026-05-20"
         */
        public ?string $notes = null,
    ) {}

    /** True when this model supports prompt caching (read or write). */
    public function supportsCache(): bool
    {
        return $this->cacheReadPerMillion !== null;
    }

    /** True when Anthropic-style cache write surcharge applies. */
    public function hasWriteSurcharge(): bool
    {
        return $this->cacheWritePerMillion !== null;
    }

    /** True when vision/multimodal image pricing is available. */
    public function supportsVision(): bool
    {
        return $this->imageInputPerMillion !== null;
    }

    /**
     * Effective image token price — falls back to standard input price when no explicit rate.
     */
    public function effectiveImagePrice(): float
    {
        return $this->imageInputPerMillion ?? $this->inputPerMillion;
    }

    public function supportsImageGeneration(): bool
    {
        return $this->imageOutputPerMillion !== null || $this->perImageCost !== null;
    }

    public function effectiveImageOutputPrice(): float
    {
        return $this->imageOutputPerMillion ?? $this->outputPerMillion;
    }

    /**
     * Return a copy with a different model identifier (useful for registering glob patterns).
     */
    public function withModel(string $model): self
    {
        return new self(
            model: $model,
            inputPerMillion: $this->inputPerMillion,
            outputPerMillion: $this->outputPerMillion,
            cacheWritePerMillion: $this->cacheWritePerMillion,
            cacheReadPerMillion: $this->cacheReadPerMillion,
            cacheReadIsSubsetOfInput: $this->cacheReadIsSubsetOfInput,
            imageInputPerMillion: $this->imageInputPerMillion,
            imageOutputPerMillion: $this->imageOutputPerMillion,
            perImageCost: $this->perImageCost,
            currency: $this->currency,
            notes: $this->notes,
        );
    }

    /**
     * Zero-cost sentinel for unknown models.
     *
     * @internal Used by PriceTable implementations when a model is not found.
     */
    public static function zero(string $model): self
    {
        return new self(model: $model, inputPerMillion: 0.0, outputPerMillion: 0.0);
    }
}
