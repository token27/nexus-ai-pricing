<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\ValueObject;

use function sprintf;

use Token27\NexusAI\Pricing\Contract\PricingResultInterface;

/**
 * Immutable result of a single LLM pricing calculation.
 *
 * All properties are set at construction time and never mutated.
 * Use add() to combine results from multi-step tool-calling loops.
 *
 * @example
 *   $result = PricingEngine::for('claude-sonnet-4-6')->calculate(
 *       inputTokens:      1_000,
 *       outputTokens:       500,
 *       cacheWriteTokens:   800,
 *       cacheReadTokens:    200,
 *   );
 *
 *   echo $result->totalCostUsd();    // 0.01015 (3.75+0.30 cache overhead applied)
 *   echo $result->cacheSavingsUsd(); // 0.00054  (vs paying full input price for 200 tokens)
 *   echo $result->format();
 */
final readonly class PricingResult implements PricingResultInterface
{
    public function __construct(
        private string $model,
        private float $inputCostUsd,
        private float $outputCostUsd,
        private float $cacheWriteCostUsd,
        private float $cacheReadCostUsd,
        private float $cacheSavingsUsd,
        private int $inputTokens,
        private int $outputTokens,
        private int $cacheWriteTokens,
        private int $cacheReadTokens,
        private string $currency,
        private bool $isUnknownModel,
        private float $imageOutputCostUsd = 0.0,
        private int $imageOutputTokens = 0,
        private int $imageCount = 0,
    ) {}

    // --- Static Factories -----------------------------------------------------

    /**
     * Build from pre-computed cost components.
     *
     * This is the primary constructor used by PricingEngine::calculate().
     *
     * @param ModelPrice $price     The price record used for calculation.
     * @param int        $inputTokens   Standard (non-cached) input tokens.
     * @param int        $outputTokens  Output tokens.
     * @param int        $cacheWriteTokens Tokens written to cache (Anthropic).
     * @param int        $cacheReadTokens  Tokens read from cache (any provider).
     * @param bool       $unknownModel     True when no price entry was found.
     */
    public static function compute(
        ModelPrice $price,
        int $inputTokens,
        int $outputTokens,
        int $cacheWriteTokens = 0,
        int $cacheReadTokens = 0,
        bool $unknownModel = false,
    ): self {
        $M = 1_000_000.0;

        $inputCost = ($inputTokens / $M) * $price->inputPerMillion;
        $outputCost = ($outputTokens / $M) * $price->outputPerMillion;

        // Cache-read adjustment: for OpenAI-style caching (subset), subtract from inputCost
        // and replace with cached rate. For Anthropic-style (additive), add separately.
        $cacheWriteCost = 0.0;
        $cacheReadCost = 0.0;
        $cacheSavings = 0.0;

        if ($cacheReadTokens > 0 && $price->cacheReadPerMillion !== null) {
            $cacheReadCost = ($cacheReadTokens / $M) * $price->cacheReadPerMillion;

            if ($price->cacheReadIsSubsetOfInput) {
                // Undo the inputPerMillion cost for cached tokens and apply discounted rate
                $inputCost -= ($cacheReadTokens / $M) * $price->inputPerMillion;
                $cacheSavings = ($cacheReadTokens / $M) * ($price->inputPerMillion - $price->cacheReadPerMillion);
            } else {
                // Anthropic: cache read tokens are additional, savings = what they would have cost
                $cacheSavings = ($cacheReadTokens / $M) * ($price->inputPerMillion - $price->cacheReadPerMillion);
            }
        }

        if ($cacheWriteTokens > 0 && $price->cacheWritePerMillion !== null) {
            $cacheWriteCost = ($cacheWriteTokens / $M) * $price->cacheWritePerMillion;
        }

        return new self(
            model: $price->model,
            inputCostUsd: max(0.0, $inputCost),
            outputCostUsd: $outputCost,
            cacheWriteCostUsd: $cacheWriteCost,
            cacheReadCostUsd: $cacheReadCost,
            cacheSavingsUsd: max(0.0, $cacheSavings),
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cacheWriteTokens: $cacheWriteTokens,
            cacheReadTokens: $cacheReadTokens,
            currency: $price->currency,
            isUnknownModel: $unknownModel,
        );
    }

    /** Zero-cost sentinel for an unknown model. */
    public static function unknown(string $model): self
    {
        return new self(
            model: $model,
            inputCostUsd: 0.0,
            outputCostUsd: 0.0,
            cacheWriteCostUsd: 0.0,
            cacheReadCostUsd: 0.0,
            cacheSavingsUsd: 0.0,
            inputTokens: 0,
            outputTokens: 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            currency: 'USD',
            isUnknownModel: true,
        );
    }

    // --- PricingResultInterface ----------------------------------------------

    public function inputCostUsd(): float
    {
        return $this->inputCostUsd;
    }
    public function outputCostUsd(): float
    {
        return $this->outputCostUsd;
    }
    public function cacheWriteCostUsd(): float
    {
        return $this->cacheWriteCostUsd;
    }
    public function cacheReadCostUsd(): float
    {
        return $this->cacheReadCostUsd;
    }
    public function cacheSavingsUsd(): float
    {
        return $this->cacheSavingsUsd;
    }
    public function inputTokens(): int
    {
        return $this->inputTokens;
    }
    public function outputTokens(): int
    {
        return $this->outputTokens;
    }
    public function cacheWriteTokens(): int
    {
        return $this->cacheWriteTokens;
    }
    public function cacheReadTokens(): int
    {
        return $this->cacheReadTokens;
    }
    public function currency(): string
    {
        return $this->currency;
    }
    public function isUnknownModel(): bool
    {
        return $this->isUnknownModel;
    }
    public function model(): string
    {
        return $this->model;
    }
    public function imageOutputCostUsd(): float
    {
        return $this->imageOutputCostUsd;
    }
    public function imageOutputTokens(): int
    {
        return $this->imageOutputTokens;
    }
    public function imageCount(): int
    {
        return $this->imageCount;
    }

    public function totalCostUsd(): float
    {
        return $this->inputCostUsd + $this->outputCostUsd
             + $this->cacheWriteCostUsd + $this->cacheReadCostUsd
             + $this->imageOutputCostUsd;
    }

    public function isZero(): bool
    {
        return $this->totalCostUsd() === 0.0;
    }

    public function format(): string
    {
        $total = number_format($this->totalCostUsd(), 6);
        $input = number_format($this->inputTokens);
        $output = number_format($this->outputTokens);

        $suffix = '';
        if ($this->imageOutputTokens > 0) {
            $suffix .= ' + ' . number_format($this->imageOutputTokens) . ' image output tokens';
        }
        if ($this->imageCount > 0) {
            $suffix .= ' (' . number_format($this->imageCount) . ' images)';
        }
        if ($this->isUnknownModel) {
            $suffix .= ' [unknown model]';
        }

        return "\${$total} {$this->currency} ({$input} input + {$output} output tokens){$suffix}";
    }

    public function formatDetailed(): string
    {
        $lines = [
            sprintf('Model:        %s%s', $this->model, $this->isUnknownModel ? ' (UNKNOWN - prices unavailable)' : ''),
            sprintf('Total:        $%s %s', number_format($this->totalCostUsd(), 6), $this->currency),
            sprintf('  Input:      $%s (%s tokens)', number_format($this->inputCostUsd, 6), number_format($this->inputTokens)),
            sprintf('  Output:     $%s (%s tokens)', number_format($this->outputCostUsd, 6), number_format($this->outputTokens)),
        ];

        if ($this->imageOutputTokens > 0) {
            $lines[] = sprintf(
                '  Image output: $%s (%s tokens)',
                number_format($this->imageOutputCostUsd, 6),
                number_format($this->imageOutputTokens),
            );
        }

        if ($this->cacheWriteTokens > 0 || $this->cacheReadTokens > 0) {
            $lines[] = sprintf('  Cache write: $%s (%s tokens)', number_format($this->cacheWriteCostUsd, 6), number_format($this->cacheWriteTokens));
            $lines[] = sprintf(
                '  Cache read:  $%s (%s tokens, saved $%s vs standard rate)',
                number_format($this->cacheReadCostUsd, 6),
                number_format($this->cacheReadTokens),
                number_format($this->cacheSavingsUsd, 6),
            );
        }

        return implode("\n", $lines);
    }

    public function toArray(): array
    {
        return [
            'model'                 => $this->model,
            'currency'              => $this->currency,
            'input_tokens'          => $this->inputTokens,
            'output_tokens'         => $this->outputTokens,
            'cache_write_tokens'    => $this->cacheWriteTokens,
            'cache_read_tokens'     => $this->cacheReadTokens,
            'input_cost_usd'        => $this->inputCostUsd,
            'output_cost_usd'       => $this->outputCostUsd,
            'cache_write_cost_usd'  => $this->cacheWriteCostUsd,
            'cache_read_cost_usd'   => $this->cacheReadCostUsd,
            'cache_savings_usd'     => $this->cacheSavingsUsd,
            'image_output_cost_usd' => $this->imageOutputCostUsd,
            'image_output_tokens'   => $this->imageOutputTokens,
            'image_count'           => $this->imageCount,
            'total_cost_usd'        => $this->totalCostUsd(),
            'is_unknown_model'      => $this->isUnknownModel,
        ];
    }

    public function add(PricingResultInterface $other): static
    {
        $otherImageOutputCostUsd = $other instanceof self ? $other->imageOutputCostUsd : 0.0;
        $otherImageOutputTokens = $other instanceof self ? $other->imageOutputTokens : 0;
        $otherImageCount = $other instanceof self ? $other->imageCount : 0;

        return new self(
            model: $this->model,
            inputCostUsd: $this->inputCostUsd + $other->inputCostUsd(),
            outputCostUsd: $this->outputCostUsd + $other->outputCostUsd(),
            cacheWriteCostUsd: $this->cacheWriteCostUsd + $other->cacheWriteCostUsd(),
            cacheReadCostUsd: $this->cacheReadCostUsd + $other->cacheReadCostUsd(),
            cacheSavingsUsd: $this->cacheSavingsUsd + $other->cacheSavingsUsd(),
            inputTokens: $this->inputTokens + $other->inputTokens(),
            outputTokens: $this->outputTokens + $other->outputTokens(),
            cacheWriteTokens: $this->cacheWriteTokens + $other->cacheWriteTokens(),
            cacheReadTokens: $this->cacheReadTokens + $other->cacheReadTokens(),
            currency: $this->currency,
            isUnknownModel: $this->isUnknownModel && $other->isUnknownModel(),
            imageOutputCostUsd: $this->imageOutputCostUsd + $otherImageOutputCostUsd,
            imageOutputTokens: $this->imageOutputTokens + $otherImageOutputTokens,
            imageCount: $this->imageCount + $otherImageCount,
        );
    }
}
