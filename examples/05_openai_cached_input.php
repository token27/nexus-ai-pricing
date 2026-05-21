<?php

/**
 * Example 05 — OpenAI Cached Input
 *
 * OpenAI's "Cached input" feature differs fundamentally from Anthropic's
 * "Prompt Caching". Understanding the difference is critical for correct billing.
 *
 * ┌─────────────────────┬──────────────────────────┬──────────────────────────┐
 * │                     │ Anthropic                │ OpenAI                   │
 * ├─────────────────────┼──────────────────────────┼──────────────────────────┤
 * │ Cache model         │ ADDITIVE — cache tokens  │ SUBSET — cached_tokens   │
 * │                     │ are separate from input   │ are PART of prompt_tokens│
 * ├─────────────────────┼──────────────────────────┼──────────────────────────┤
 * │ API fields          │ input_tokens +            │ usage.prompt_tokens +    │
 * │                     │ cache_creation_tokens +   │ usage.prompt_tokens_     │
 * │                     │ cache_read_tokens         │ details.cached_tokens    │
 * ├─────────────────────┼──────────────────────────┼──────────────────────────┤
 * │ Billing             │ input_cost + cache_write  │ non_cached_cost +        │
 * │                     │ _cost + cache_read_cost   │ cached_cost (discount)   │
 * ├─────────────────────┼──────────────────────────┼──────────────────────────┤
 * │ cacheReadIsSubset   │ false                    │ true                     │
 * │ OfInput flag        │                          │                          │
 * └─────────────────────┴──────────────────────────┴──────────────────────────┘
 *
 * OpenAI Cached Input pricing (gpt-4o):
 *   - Standard input:   $2.50/M tokens
 *   - Cached input:     $1.25/M tokens  (50% discount — automatic, no write cost)
 *   - Output:           $10.00/M tokens
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Engine\PricingEngine;

$model = 'gpt-4o';

// ─── OpenAI API response mapping ──────────────────────────────────────────────
//
// When you receive an OpenAI response, the usage object looks like:
//   {
//     "prompt_tokens":              5200,   ← total prompt tokens (includes cached)
//     "completion_tokens":           400,
//     "prompt_tokens_details": {
//       "cached_tokens":            5000    ← subset of prompt_tokens, billed at 50%
//     }
//   }
//
// Map to calculate():
//   inputTokens     = usage.prompt_tokens          = 5200 (full prompt)
//   cacheReadTokens = usage.prompt_tokens_details.cached_tokens = 5000 (subset)

$result = PricingEngine::for($model)->calculate(
    inputTokens: 5_200,   // prompt_tokens (full, including the cached portion)
    outputTokens: 400,   // completion_tokens
    cacheReadTokens: 5_000,  // prompt_tokens_details.cached_tokens (subset at 50%)
);

echo "=== OpenAI Cached Input (gpt-4o) ===" . PHP_EOL;
echo $result->formatDetailed() . PHP_EOL;
echo PHP_EOL;

// ─── Verify the math manually ─────────────────────────────────────────────────
//
// Non-cached portion: (5200 - 5000) = 200 tokens → $2.50/M = $0.000500
// Cached portion:      5000 tokens  → $1.25/M = $0.006250 (50% off $2.50/M)
// Output:              400 tokens   → $10.00/M = $0.004000
// Total: $0.000500 + $0.006250 + $0.004000 = $0.010750

echo "=== Manual verification ===" . PHP_EOL;
printf('Non-cached input:  $%.6f (200 tokens @ $2.50/M)%s', 200 / 1_000_000 * 2.50, PHP_EOL);
printf('Cached input:      $%.6f (5000 tokens @ $1.25/M)%s', 5_000 / 1_000_000 * 1.25, PHP_EOL);
printf('Output:            $%.6f (400 tokens @ $10.00/M)%s', 400 / 1_000_000 * 10.00, PHP_EOL);
printf('Expected total:    $%.6f%s', (200 / 1_000_000 * 2.50) + (5_000 / 1_000_000 * 1.25) + (400 / 1_000_000 * 10.00), PHP_EOL);
printf('Library total:     $%.6f%s', $result->totalCostUsd(), PHP_EOL);
echo PHP_EOL;

// ─── Savings comparison ───────────────────────────────────────────────────────
//
// How much was saved vs paying full input rate for all 5200 tokens?

$withoutCache = PricingEngine::for($model)->calculate(
    inputTokens: 5_200,  // all tokens at standard rate
    outputTokens: 400,
);

echo "=== Savings comparison ===" . PHP_EOL;
printf('Without caching:    $%.6f%s', $withoutCache->totalCostUsd(), PHP_EOL);
printf('With cached input:  $%.6f%s', $result->totalCostUsd(), PHP_EOL);
printf('Saved:              $%.6f%s', $result->cacheSavingsUsd(), PHP_EOL);
printf('Savings %%:          %.1f%%%s', ($result->cacheSavingsUsd() / $withoutCache->totalCostUsd()) * 100, PHP_EOL);
echo PHP_EOL;

// ─── Anthropic vs OpenAI side-by-side on identical token counts ───────────────
//
// Same 5200 input + 5000 cached + 400 output — different billing models.

$anthropicModel = 'claude-sonnet-4-6';
$openaiModel    = 'gpt-4o';

// Anthropic: cache_read is ADDITIVE (separate from input_tokens)
$anthropicResult = PricingEngine::for($anthropicModel)->calculate(
    inputTokens: 200,    // only non-cached input (cache_read is separate)
    outputTokens: 400,
    cacheReadTokens: 5_000, // additive — full 5000 read from cache
);

// OpenAI: cache_read is SUBSET (included in prompt_tokens)
$openaiResult = PricingEngine::for($openaiModel)->calculate(
    inputTokens: 5_200,  // full prompt (includes the 5000 cached subset)
    outputTokens: 400,
    cacheReadTokens: 5_000, // subset flag — 5000 of the 5200 are cached
);

echo "=== Anthropic vs OpenAI caching (same logical request) ===" . PHP_EOL;
printf('Anthropic (%s): %s%s', $anthropicModel, $anthropicResult->format(), PHP_EOL);
printf('OpenAI    (%s):        %s%s', $openaiModel, $openaiResult->format(), PHP_EOL);
printf('%s', PHP_EOL);
printf('Anthropic cache savings: $%.6f%s', $anthropicResult->cacheSavingsUsd(), PHP_EOL);
printf('OpenAI    cache savings: $%.6f%s', $openaiResult->cacheSavingsUsd(), PHP_EOL);
echo PHP_EOL;

// ─── Multi-turn conversation with automatic caching ───────────────────────────
//
// OpenAI automatically caches prompts > 1024 tokens. No explicit write step.
// Each subsequent request referencing the same cached context gets the discount.

echo "=== Multi-turn conversation ===" . PHP_EOL;

$turns = [
    // Turn 1: no cache yet — full standard pricing
    ['input' => 5_200, 'output' => 150, 'cached' => 0,     'label' => 'Turn 1 (cold)'],
    // Turn 2: 5000 tokens auto-cached from turn 1's system prompt
    ['input' => 5_300, 'output' => 200, 'cached' => 5_000, 'label' => 'Turn 2 (cached)'],
    // Turn 3: same cache hit
    ['input' => 5_400, 'output' => 180, 'cached' => 5_000, 'label' => 'Turn 3 (cached)'],
    // Turn 4: same cache hit
    ['input' => 5_500, 'output' => 210, 'cached' => 5_000, 'label' => 'Turn 4 (cached)'],
];

$sessionTotal = null;

foreach ($turns as $turn) {
    $r = PricingEngine::for($openaiModel)->calculate(
        inputTokens: $turn['input'],
        outputTokens: $turn['output'],
        cacheReadTokens: $turn['cached'],
    );

    printf('%-22s %s  (saved $%.6f)%s', $turn['label'] . ':', $r->format(), $r->cacheSavingsUsd(), PHP_EOL);

    $sessionTotal = $sessionTotal === null ? $r : $sessionTotal->add($r);
}

echo PHP_EOL;
printf('Session total:         $%.6f%s', $sessionTotal->totalCostUsd(), PHP_EOL);
printf('Total cache savings:   $%.6f%s', $sessionTotal->cacheSavingsUsd(), PHP_EOL);
