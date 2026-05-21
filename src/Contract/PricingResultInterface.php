<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Contract;

/**
 * Rich, immutable result of a pricing calculation or estimation.
 *
 * Every method is a pure accessor — no state mutation ever happens.
 * To combine results from multiple pipeline steps, use add() which returns a new instance.
 *
 * @example
 *   $result = PricingEngine::for('gpt-4o')->calculate(inputTokens: 1200, outputTokens: 350);
 *   echo $result->totalCostUsd();   // 0.006500
 *   echo $result->format();         // "$0.006500 USD (1,200 input + 350 output tokens)"
 *
 *   // Accumulate across a multi-step tool-calling loop
 *   $total = $step1->add($step2)->add($step3);
 *   echo $total->totalCostUsd();
 */
interface PricingResultInterface
{
    // ─── Costs in USD ────────────────────────────────────────────────────────

    public function inputCostUsd(): float;
    public function outputCostUsd(): float;
    public function totalCostUsd(): float;

    // ─── Cache costs (Anthropic Prompt Caching) ───────────────────────────────

    /** Cost of tokens written to the prompt cache (Anthropic cache write surcharge). */
    public function cacheWriteCostUsd(): float;

    /** Cost of tokens read from the prompt cache (Anthropic cache read discount). */
    public function cacheReadCostUsd(): float;

    /** Total cache-related savings (input price − cache read price) × cacheReadTokens. */
    public function cacheSavingsUsd(): float;

    // ─── Token counts ─────────────────────────────────────────────────────────

    public function inputTokens(): int;
    public function outputTokens(): int;
    public function cacheWriteTokens(): int;
    public function cacheReadTokens(): int;

    // ─── Metadata ─────────────────────────────────────────────────────────────

    /** Currency code, always 'USD' in the built-in implementation. */
    public function currency(): string;

    /**
     * True when the model was not found in any price table and cost was calculated as zero.
     *
     * The library never throws exceptions for unknown models; check this flag instead.
     */
    public function isUnknownModel(): bool;

    /** Model identifier that produced this result. */
    public function model(): string;

    // ─── Presentation ─────────────────────────────────────────────────────────

    /**
     * Compact one-line summary.
     *
     * Example: "$0.006500 USD (1,200 input + 350 output tokens)"
     */
    public function format(): string;

    /**
     * Verbose multi-line breakdown including cache details when applicable.
     *
     * Example:
     *   Total: $0.006500 USD
     *   Input: $0.003000 (1,200 tokens @ $2.50/M)
     *   Output: $0.003500 (350 tokens @ $10.00/M)
     *   Cache write: $0.000000 (0 tokens)
     *   Cache read: $0.000000 (0 tokens, saved $0.000000)
     */
    public function formatDetailed(): string;

    // ─── Serialization ────────────────────────────────────────────────────────

    /**
     * @return array{
     *     model: string,
     *     currency: string,
     *     input_tokens: int,
     *     output_tokens: int,
     *     cache_write_tokens: int,
     *     cache_read_tokens: int,
     *     input_cost_usd: float,
     *     output_cost_usd: float,
     *     cache_write_cost_usd: float,
     *     cache_read_cost_usd: float,
     *     cache_savings_usd: float,
     *     total_cost_usd: float,
     *     is_unknown_model: bool,
     * }
     */
    public function toArray(): array;

    /** True when total cost is exactly 0.0 (zero tokens or unknown model). */
    public function isZero(): bool;

    // ─── Operations ───────────────────────────────────────────────────────────

    /**
     * Combine this result with another, summing all token counts and costs.
     *
     * Returns a new immutable instance. Useful for tool-calling loops:
     *
     * @example
     *   $total = $step1Result->add($step2Result)->add($step3Result);
     */
    public function add(PricingResultInterface $other): static;
}
