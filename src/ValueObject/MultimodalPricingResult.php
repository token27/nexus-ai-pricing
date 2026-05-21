<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\ValueObject;

use function sprintf;

use Token27\NexusAI\Pricing\Contract\PricingResultInterface;

/**
 * Pricing result for requests that include images (vision / multimodal prompts).
 *
 * Extends the standard text-only result with a breakdown of image token costs.
 *
 * @example
 *   $result = PricingEngine::for('gpt-4o')->estimateWithImages(
 *       text:   'What objects are in this image?',
 *       images: [ImageAttachment::highDetail(1024, 768)],
 *   );
 *   echo $result->imageTokens();   // 765
 *   echo $result->imageCostUsd();  // $0.001913
 *   echo $result->totalCostUsd();  // text cost + image cost
 *   echo $result->format();
 */
final readonly class MultimodalPricingResult implements PricingResultInterface
{
    public function __construct(
        private PricingResultInterface $textResult,
        private float                  $imageCostUsd,
        private int                    $imageTokens,
    ) {}

    // ─── Image-specific accessors ─────────────────────────────────────────────

    public function imageCostUsd(): float
    {
        return $this->imageCostUsd;
    }
    public function imageTokens(): int
    {
        return $this->imageTokens;
    }

    /** The text-only portion of this result (without image costs). */
    public function textResult(): PricingResultInterface
    {
        return $this->textResult;
    }

    // ─── PricingResultInterface — delegated with image totals ─────────────────

    public function inputCostUsd(): float
    {
        return $this->textResult->inputCostUsd() + $this->imageCostUsd;
    }
    public function outputCostUsd(): float
    {
        return $this->textResult->outputCostUsd();
    }
    public function cacheWriteCostUsd(): float
    {
        return $this->textResult->cacheWriteCostUsd();
    }
    public function cacheReadCostUsd(): float
    {
        return $this->textResult->cacheReadCostUsd();
    }
    public function cacheSavingsUsd(): float
    {
        return $this->textResult->cacheSavingsUsd();
    }
    public function inputTokens(): int
    {
        return $this->textResult->inputTokens() + $this->imageTokens;
    }
    public function outputTokens(): int
    {
        return $this->textResult->outputTokens();
    }
    public function cacheWriteTokens(): int
    {
        return $this->textResult->cacheWriteTokens();
    }
    public function cacheReadTokens(): int
    {
        return $this->textResult->cacheReadTokens();
    }
    public function currency(): string
    {
        return $this->textResult->currency();
    }
    public function isUnknownModel(): bool
    {
        return $this->textResult->isUnknownModel();
    }
    public function model(): string
    {
        return $this->textResult->model();
    }
    public function isZero(): bool
    {
        return $this->totalCostUsd() === 0.0;
    }

    public function totalCostUsd(): float
    {
        return $this->textResult->totalCostUsd() + $this->imageCostUsd;
    }

    public function format(): string
    {
        $total      = number_format($this->totalCostUsd(), 6);
        $textTok    = number_format($this->textResult->inputTokens());
        $imageTok   = number_format($this->imageTokens);
        $outputTok  = number_format($this->outputTokens());

        return "\${$total} {$this->currency()} ({$textTok} text + {$imageTok} image + {$outputTok} output tokens)";
    }

    public function formatDetailed(): string
    {
        return $this->textResult->formatDetailed()
            . "\n"
            . sprintf('  Image:      $%s (%s tokens)', number_format($this->imageCostUsd, 6), number_format($this->imageTokens));
    }

    public function toArray(): array
    {
        return array_merge($this->textResult->toArray(), [
            'image_tokens'   => $this->imageTokens,
            'image_cost_usd' => $this->imageCostUsd,
            'total_cost_usd' => $this->totalCostUsd(),
        ]);
    }

    public function add(PricingResultInterface $other): static
    {
        $otherImage = $other instanceof self ? $other->imageTokens : 0;
        $otherImgCost = $other instanceof self ? $other->imageCostUsd : 0.0;

        return new self(
            textResult: $this->textResult->add($other instanceof self ? $other->textResult : $other),
            imageCostUsd: $this->imageCostUsd + $otherImgCost,
            imageTokens: $this->imageTokens  + $otherImage,
        );
    }
}
