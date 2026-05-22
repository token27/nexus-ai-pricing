<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\ValueObject;

use Token27\NexusAI\Pricing\Contract\UsageInterface;

readonly class Usage implements UsageInterface
{
    public function __construct(
        public int $textInputTokens = 0,
        public int $textOutputTokens = 0,
        public int $imageInputTokens = 0,
        public int $imageOutputTokens = 0,
        public ?int $cacheWriteTokens = null,
        public ?int $cacheReadTokens = null,
    ) {}

    public function textInputTokens(): int
    {
        return $this->textInputTokens;
    }
    public function imageInputTokens(): int
    {
        return $this->imageInputTokens;
    }
    public function textOutputTokens(): int
    {
        return $this->textOutputTokens;
    }
    public function imageOutputTokens(): int
    {
        return $this->imageOutputTokens;
    }
    public function cacheWriteTokens(): ?int
    {
        return $this->cacheWriteTokens;
    }
    public function cacheReadTokens(): ?int
    {
        return $this->cacheReadTokens;
    }

    public function totalInputTokens(): int
    {
        return $this->textInputTokens + $this->imageInputTokens;
    }

    public function totalOutputTokens(): int
    {
        return $this->textOutputTokens + $this->imageOutputTokens;
    }

    public function totalTokens(): int
    {
        return $this->totalInputTokens() + $this->totalOutputTokens();
    }

    // ——— Factories por proveedor —————————————————————————

    /**
     * Desde el nodo 'usage' de OpenAI (chat completions o image generations).
     * Extrae input_tokens_details y output_tokens_details con breakdown texto/imagen.
     *
     * @param array<string, mixed> $usageData
     */
    public static function fromOpenAI(array $usageData): self
    {
        $in = $usageData['input_tokens_details'] ?? [];
        $out = $usageData['output_tokens_details'] ?? [];

        return new self(
            textInputTokens: $in['text_tokens'] ?? 0,
            imageInputTokens: $in['image_tokens'] ?? 0,
            textOutputTokens: $out['text_tokens'] ?? 0,
            imageOutputTokens: $out['image_tokens'] ?? 0,
        );
    }

    /**
     * Desde el nodo 'usage' de Anthropic Messages API.
     *
     * @param array<string, mixed> $usageData
     */
    public static function fromAnthropic(array $usageData): self
    {
        return new self(
            textInputTokens: $usageData['input_tokens'] ?? 0,
            textOutputTokens: $usageData['output_tokens'] ?? 0,
            cacheWriteTokens: isset($usageData['cache_creation_input_tokens'])
                ? (int) $usageData['cache_creation_input_tokens'] : null,
            cacheReadTokens: isset($usageData['cache_read_input_tokens'])
                ? (int) $usageData['cache_read_input_tokens'] : null,
        );
    }

    /**
     * Desde el nodo 'usageMetadata' de Gemini generateContent.
     *
     * @param array<string, mixed> $usageMeta
     */
    public static function fromGemini(array $usageMeta): self
    {
        return new self(
            textInputTokens: $usageMeta['promptTokenCount'] ?? 0,
            textOutputTokens: $usageMeta['candidatesTokenCount'] ?? 0,
        );
    }

    /**
     * Factory legacy para migración desde código que aún usa promptTokens/completionTokens.
     * Asume que todo el input/output es texto (sin breakdown disponible).
     * Los drivers modernos deben usar fromOpenAI/fromAnthropic/fromGemini.
     */
    public static function fromLegacy(int $promptTokens, int $completionTokens, int $imageTokens = 0): self
    {
        return new self(
            textInputTokens: $promptTokens,
            textOutputTokens: $completionTokens,
            imageOutputTokens: $imageTokens,
        );
    }
}
