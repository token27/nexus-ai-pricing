<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\ValueObject;

/**
 * Descriptor for a single image in a multimodal prompt.
 *
 * Used by PricingEngine::estimateWithImages() to compute the token cost
 * of each image before delegating to the provider-specific formula in nexus-ai-tokenizer.
 *
 * @example
 *   $result = PricingEngine::for('gpt-4o')->estimateWithImages(
 *       text:   'Describe this chart.',
 *       images: [
 *           ImageAttachment::highDetail(1920, 1080),
 *           ImageAttachment::lowDetail(800, 600),
 *       ],
 *   );
 *   echo $result->imageTokens();   // 1,360 (tile-based OpenAI formula)
 *   echo $result->totalCostUsd();  // $0.003400
 */
final readonly class ImageAttachment
{
    /** @param string $detail 'low', 'high', or 'auto'. */
    public function __construct(
        public int    $widthPx,
        public int    $heightPx,
        public string $detail = 'auto',
    ) {}

    public static function lowDetail(int $widthPx, int $heightPx): self
    {
        return new self($widthPx, $heightPx, 'low');
    }

    public static function highDetail(int $widthPx, int $heightPx): self
    {
        return new self($widthPx, $heightPx, 'high');
    }

    public static function auto(int $widthPx, int $heightPx): self
    {
        return new self($widthPx, $heightPx, 'auto');
    }
}
