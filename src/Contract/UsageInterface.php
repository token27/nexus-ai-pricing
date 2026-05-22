<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Contract;

interface UsageInterface
{
    /** Tokens de texto enviados al modelo (input) */
    public function textInputTokens(): int;

    /** Tokens de imagen enviados al modelo — vision input */
    public function imageInputTokens(): int;

    /** Tokens de texto generados por el modelo (output) */
    public function textOutputTokens(): int;

    /** Tokens de imagen generados por el modelo — image generation output */
    public function imageOutputTokens(): int;

    /** Tokens escritos en caché (Anthropic) */
    public function cacheWriteTokens(): ?int;

    /** Tokens leídos de caché */
    public function cacheReadTokens(): ?int;

    /** Suma de todos los tokens de entrada */
    public function totalInputTokens(): int;

    /** Suma de todos los tokens de salida */
    public function totalOutputTokens(): int;

    /** Suma total */
    public function totalTokens(): int;
}
