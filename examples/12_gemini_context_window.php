<?php

/**
 * Example 12 — Gemini Context Window & Tiered Pricing
 *
 * Google Gemini models use context-window-based tiered pricing:
 *   - Below 200K tokens: standard rate (what the catalog provides by default)
 *   - Above 200K tokens: higher per-token rate
 *
 * The catalog stores the sub-200K rate. For prompts that exceed the threshold,
 * inject a custom price via ChainedPriceTable or registerPrice().
 *
 * This example covers:
 *   1. Standard Gemini 2.5 Pro pricing (< 200K context)
 *   2. Overriding to the >200K tier for large-context requests
 *   3. Gemini context caching (subset-style, automatic — no write surcharge)
 *   4. Gemini Flash vs Pro comparison
 *   5. Vision/multimodal with Gemini 2.5 Pro
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\PriceTable\ChainedPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

// ─── 1. Standard Gemini 2.5 Pro pricing (< 200K context) ─────────────────────
//
// Default catalog rate: $1.25/M input, $10.00/M output
// Applies when total context (prompt + output) stays below ~200K tokens.

echo "=== 1. Gemini 2.5 Pro standard rate (< 200K context) ===" . PHP_EOL;

$standardResult = PricingEngine::for('gemini-2.5-pro')->calculate(
    inputTokens: 50_000,
    outputTokens: 2_000,
);

echo $standardResult->formatDetailed() . PHP_EOL;
printf('  Input rate:  $1.25/M tokens%s', PHP_EOL);
printf('  Output rate: $10.00/M tokens%s', PHP_EOL);
echo PHP_EOL;

// ─── 2. High-context tier override (> 200K tokens) ───────────────────────────
//
// For requests where the full context window exceeds 200K tokens,
// inject a custom ModelPrice with the higher published rate.
// Source: https://ai.google.dev/pricing (check current rates before deploying)
//
// gemini-2.5-pro > 200K tier (approximate, verify against official docs):
//   Input:  $2.50/M   Output: $15.00/M   Cache: $0.25/M

$highContextPrice = new ModelPrice(
    model: 'gemini-2.5-pro-high-context',
    inputPerMillion: 2.50,
    outputPerMillion: 15.00,
    cacheReadPerMillion: 0.25,
    cacheReadIsSubsetOfInput: true,    // Gemini uses subset-style caching
    notes: 'Gemini 2.5 Pro > 200K context tier | Verify rate before use',
);

// Chain: high-context price first, default catalog as fallback for everything else
$tieredEngine = PricingEngine::withTable(
    new ChainedPriceTable(
        new ArrayPriceTable([$highContextPrice]),
        new ArrayPriceTable(DefaultPriceCatalog::get()),
    ),
);

echo "=== 2. Gemini 2.5 Pro high-context tier (> 200K) ===" . PHP_EOL;

$largeContextResult = $tieredEngine->make('gemini-2.5-pro-high-context')->calculate(
    inputTokens: 250_000,
    outputTokens: 3_000,
);

echo $largeContextResult->formatDetailed() . PHP_EOL;

// Compare the cost difference between tiers for the same token counts
$standardLargeResult = PricingEngine::for('gemini-2.5-pro')->calculate(
    inputTokens: 250_000,
    outputTokens: 3_000,
);

printf('  Standard rate ($1.25/M):   $%.4f%s', $standardLargeResult->totalCostUsd(), PHP_EOL);
printf('  High-context ($2.50/M):    $%.4f%s', $largeContextResult->totalCostUsd(), PHP_EOL);
printf(
    '  Extra cost for high tier:  $%.4f%s',
    $largeContextResult->totalCostUsd() - $standardLargeResult->totalCostUsd(),
    PHP_EOL,
);
echo PHP_EOL;

// ─── 3. Gemini context caching (subset-style) ─────────────────────────────────
//
// Gemini automatically caches prompt prefixes (subset model — same as OpenAI).
// No write surcharge. The API returns:
//   usage.prompt_token_count     → total tokens including cached
//   usage.cached_content_token_count → subset of above, billed at cached rate
//
// Map to calculate():
//   inputTokens     = usage.prompt_token_count               (full prompt)
//   cacheReadTokens = usage.cached_content_token_count       (cached subset)

echo "=== 3. Gemini context caching (subset-style) ===" . PHP_EOL;

// Simulate a Gemini API response where 80K tokens were cached:
$geminiApiUsage = [
    'prompt_token_count'            => 85_000,  // full context
    'cached_content_token_count'    => 80_000,  // subset served from cache
    'candidates_token_count'        => 1_500,   // output
];

$cachedGeminiResult = PricingEngine::for('gemini-2.5-pro')->calculate(
    inputTokens: $geminiApiUsage['prompt_token_count'],
    outputTokens: $geminiApiUsage['candidates_token_count'],
    cacheReadTokens: $geminiApiUsage['cached_content_token_count'],
);

echo $cachedGeminiResult->formatDetailed() . PHP_EOL;
printf('  Cache savings this request: $%.4f%s', $cachedGeminiResult->cacheSavingsUsd(), PHP_EOL);

$withoutCache = PricingEngine::for('gemini-2.5-pro')->calculate(
    inputTokens: 85_000,
    outputTokens: 1_500,
);
printf('  Without caching:            $%.4f%s', $withoutCache->totalCostUsd(), PHP_EOL);
printf('  With caching:               $%.4f%s', $cachedGeminiResult->totalCostUsd(), PHP_EOL);
echo PHP_EOL;

// ─── 4. Gemini Flash vs Pro comparison ────────────────────────────────────────
//
// Flash: faster, cheaper, lower capability
// Pro:   slower, more expensive, higher capability
// Both support context caching at the same discount ratio.

echo "=== 4. Flash vs Pro comparison ===" . PHP_EOL;
echo str_pad('Model', 30) . str_pad('Input $/M', 12) . str_pad('Output $/M', 12) . str_pad('1K+500 cost', 14) . 'Cache read $/M' . PHP_EOL;
echo str_repeat('─', 80) . PHP_EOL;

$comparisonModels = [
    'gemini-2.5-pro'    => ['input' => '$1.25', 'output' => '$10.00', 'cacheRead' => '$0.125'],
    'gemini-2.5-flash'  => ['input' => '$0.30', 'output' => '$2.50',  'cacheRead' => '$0.03'],
    'gemini-3.5-flash'  => ['input' => '$1.50', 'output' => '$9.00',  'cacheRead' => '$0.15'],
];

foreach ($comparisonModels as $model => $rates) {
    $r = PricingEngine::for($model)->calculate(inputTokens: 1_000, outputTokens: 500);
    printf(
        '%-30s %-12s %-12s $%-13.6f %s%s',
        $model,
        $rates['input'],
        $rates['output'],
        $r->totalCostUsd(),
        $rates['cacheRead'],
        PHP_EOL,
    );
}
echo PHP_EOL;

// ─── 5. Gemini Vision: multimodal pricing ────────────────────────────────────
//
// Gemini uses 768×768 tiles at 258 tokens per tile for image token counting.
// Requires nexus-ai-tokenizer to be installed for actual estimation.
// Image tokens are billed at the standard input rate (same $/M for Gemini).

echo "=== 5. Gemini Vision multimodal ===" . PHP_EOL;

$visionResult = PricingEngine::for('gemini-2.5-pro')->estimateWithImages(
    text: 'Analyse the objects in these images and provide a detailed description.',
    images: [
        ImageAttachment::highDetail(1920, 1080),  // large diagram
        ImageAttachment::highDetail(1280, 720),   // medium screenshot
        ImageAttachment::lowDetail(640, 480),     // small thumbnail
    ],
);

printf('  Text tokens:   %s%s', number_format($visionResult->inputTokens() - $visionResult->imageTokens()), PHP_EOL);
printf(
    '  Image tokens:  %s (Gemini tile formula: 768px tiles × 258 tokens)%s',
    number_format($visionResult->imageTokens()),
    PHP_EOL,
);
printf('  Image cost:    $%.6f%s', $visionResult->imageCostUsd(), PHP_EOL);
printf('  Total:         %s%s', $visionResult->format(), PHP_EOL);
