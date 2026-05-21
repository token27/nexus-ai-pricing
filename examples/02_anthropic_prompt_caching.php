<?php

/**
 * Example 02 — Anthropic Prompt Caching
 *
 * Demonstrates how to correctly calculate costs when using Anthropic's Prompt Caching feature.
 *
 * Prompt Caching pricing (Sonnet 4.6):
 *   - Standard input:    $3.00/M tokens
 *   - Cache write:       $3.75/M tokens  (1.25× — first request, creates cache block)
 *   - Cache read:        $0.30/M tokens  (0.10× — subsequent requests, 90% discount!)
 *   - Output:            $15.00/M tokens
 *
 * The savings become significant when a long system prompt (e.g., 5,000 tokens) is
 * reused across many requests — you only pay the cache write once.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Engine\PricingEngine;

$model = 'claude-sonnet-4-6';

// ─── Scenario: first request (cache WRITE) ────────────────────────────────────
//
// From Anthropic API response body:
//   usage.input_tokens                = 200  (non-cached input tokens)
//   usage.cache_creation_input_tokens = 5000 (tokens written to 5-min cache — surcharge)
//   usage.cache_read_input_tokens     = 0
//   usage.output_tokens               = 400

$firstRequest = PricingEngine::for($model)->calculate(
    inputTokens: 200,
    outputTokens: 400,
    cacheWriteTokens: 5_000,  // paid at $3.75/M (1.25× surcharge)
    cacheReadTokens: 0,
);

echo "=== First request (cache WRITE) ===" . PHP_EOL;
echo $firstRequest->formatDetailed() . PHP_EOL;
echo PHP_EOL;

// ─── Scenario: subsequent request (cache READ) ────────────────────────────────
//
// Same system prompt now served from cache at 90% discount:
//   usage.input_tokens                = 200  (only the new user message)
//   usage.cache_creation_input_tokens = 0
//   usage.cache_read_input_tokens     = 5000 (served from cache — huge discount!)
//   usage.output_tokens               = 400

$cachedRequest = PricingEngine::for($model)->calculate(
    inputTokens: 200,
    outputTokens: 400,
    cacheWriteTokens: 0,
    cacheReadTokens: 5_000,  // paid at $0.30/M (0.10× = 90% off!)
);

echo "=== Subsequent request (cache READ) ===" . PHP_EOL;
echo $cachedRequest->formatDetailed() . PHP_EOL;
echo PHP_EOL;

// ─── Savings comparison ───────────────────────────────────────────────────────

$withoutCache = PricingEngine::for($model)->calculate(
    inputTokens: 5_200,  // full 5,200 tokens at standard rate
    outputTokens: 400,
);

echo "=== Savings comparison ===" . PHP_EOL;
printf('Without caching:         $%.6f%s', $withoutCache->totalCostUsd(), PHP_EOL);
printf('With cache read:         $%.6f%s', $cachedRequest->totalCostUsd(), PHP_EOL);
printf('Cache savings (1 read):  $%.6f%s', $cachedRequest->cacheSavingsUsd(), PHP_EOL);
printf('Cache savings accessor:  $%.6f%s', $cachedRequest->cacheSavingsUsd(), PHP_EOL);
echo PHP_EOL;

// ─── Break-even analysis ──────────────────────────────────────────────────────
//
// After how many requests does caching pay off?

$writeExtraCost = $firstRequest->cacheWriteCostUsd() - (5_000 / 1_000_000 * 3.00);
$savingsPerRead = $cachedRequest->cacheSavingsUsd();
$breakEvenReads = $writeExtraCost > 0 ? (int) ceil($writeExtraCost / $savingsPerRead) : 1;

echo "=== Break-even analysis ===" . PHP_EOL;
printf('Extra cost of cache write: $%.6f%s', $writeExtraCost, PHP_EOL);
printf('Saving per cached read:    $%.6f%s', $savingsPerRead, PHP_EOL);
printf('Break-even after:          %d read(s)%s', $breakEvenReads, PHP_EOL);
echo 'Verdict: caching is worth it after just 1-2 requests!' . PHP_EOL;
echo PHP_EOL;

// ─── Accumulate cost across a session ────────────────────────────────────────
//
// Sum costs across multiple turns in a conversation.

$turn1 = PricingEngine::for($model)->calculate(inputTokens: 200, outputTokens: 350, cacheWriteTokens: 5_000);
$turn2 = PricingEngine::for($model)->calculate(inputTokens: 200, outputTokens: 280, cacheReadTokens: 5_000);
$turn3 = PricingEngine::for($model)->calculate(inputTokens: 200, outputTokens: 310, cacheReadTokens: 5_000);

$session = $turn1->add($turn2)->add($turn3);

echo "=== 3-turn session total ===" . PHP_EOL;
echo $session->formatDetailed() . PHP_EOL;
printf('Total session cost: $%.6f%s', $session->totalCostUsd(), PHP_EOL);
printf('Total cache saved:  $%.6f%s', $session->cacheSavingsUsd(), PHP_EOL);
