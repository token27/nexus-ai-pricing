<?php

/**
 * Example 11 — Cost Reporting & Serialization
 *
 * Demonstrates all the ways to present, serialize, and log PricingResult objects.
 *
 *   1. format()         — compact one-liner for inline logs and terminal output
 *   2. formatDetailed() — multi-line verbose breakdown for debug / audit logs
 *   3. toArray()        — structured data for JSON, database storage, or API responses
 *   4. Accumulating session results and printing a summary report
 *   5. Building a JSON audit log entry from a real API usage payload
 *   6. MultimodalPricingResult — the extended toArray() with image fields
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;

// ─── 1. format() — compact one-liner ─────────────────────────────────────────
//
// Best for: application logs, CLI output, quick assertions in tests.
// Pattern: "$0.006500 USD (1,200 input + 350 output tokens)"

$result = PricingEngine::for('gpt-4o')->calculate(inputTokens: 1_200, outputTokens: 350);

echo "=== 1. format() — compact one-liner ===" . PHP_EOL;
echo $result->format() . PHP_EOL;
// Output: $0.006500 USD (1,200 input + 350 output tokens)
echo PHP_EOL;

// ─── 2. formatDetailed() — verbose multi-line breakdown ──────────────────────
//
// Best for: debug logging, billing audits, support tickets.
// Includes per-component costs (input, output, cache write/read, savings).

$cachedResult = PricingEngine::for('claude-sonnet-4-6')->calculate(
    inputTokens: 350,
    outputTokens: 820,
    cacheWriteTokens: 4_800,
    cacheReadTokens: 0,
);

echo "=== 2. formatDetailed() — cache write example ===" . PHP_EOL;
echo $cachedResult->formatDetailed() . PHP_EOL;
echo PHP_EOL;

$readResult = PricingEngine::for('claude-sonnet-4-6')->calculate(
    inputTokens: 350,
    outputTokens: 820,
    cacheWriteTokens: 0,
    cacheReadTokens: 4_800,
);

echo "=== 2b. formatDetailed() — cache read example ===" . PHP_EOL;
echo $readResult->formatDetailed() . PHP_EOL;
echo PHP_EOL;

// ─── 3. toArray() — structured serialization ──────────────────────────────────
//
// Best for: JSON API responses, database writes, inter-service messages.
// Returns all fields with snake_case keys.

$serializable = PricingEngine::for('gpt-4o')->calculate(
    inputTokens: 2_000,
    outputTokens: 500,
    cacheReadTokens: 1_800,
);

echo "=== 3. toArray() — full field breakdown ===" . PHP_EOL;
$data = $serializable->toArray();
foreach ($data as $key => $value) {
    if (is_bool($value)) {
        printf("  %-28s %s%s", $key . ':', $value ? 'true' : 'false', PHP_EOL);
    } elseif (is_float($value)) {
        printf("  %-28s %.8f%s", $key . ':', $value, PHP_EOL);
    } else {
        printf("  %-28s %s%s", $key . ':', $value, PHP_EOL);
    }
}
echo PHP_EOL;

// JSON round-trip example (useful for storage or API transport)
$json = json_encode($serializable->toArray(), JSON_PRETTY_PRINT);
echo "=== JSON output ===" . PHP_EOL;
echo $json . PHP_EOL;
echo PHP_EOL;

// ─── 4. Accumulate session results and report ─────────────────────────────────
//
// Use add() to combine costs from multiple LLM calls in a session.
// add() is immutable — each call returns a new PricingResult.

echo "=== 4. Session accumulation report ===" . PHP_EOL;

$turns = [
    ['label' => 'User message + system prompt', 'input' => 1_200, 'output' => 320, 'cw' => 4_800, 'cr' => 0],
    ['label' => 'Tool call result',              'input' => 850,   'output' => 450, 'cw' => 0,     'cr' => 4_800],
    ['label' => 'Follow-up question',            'input' => 1_050, 'output' => 380, 'cw' => 0,     'cr' => 4_800],
    ['label' => 'Final synthesis',               'input' => 1_400, 'output' => 2_100, 'cw' => 0,   'cr' => 4_800],
];

$session = null;

foreach ($turns as $turn) {
    $r = PricingEngine::for('claude-sonnet-4-6')->calculate(
        inputTokens: $turn['input'],
        outputTokens: $turn['output'],
        cacheWriteTokens: $turn['cw'],
        cacheReadTokens: $turn['cr'],
    );
    printf("  %-35s  %s%s", $turn['label'] . ':', $r->format(), PHP_EOL);
    $session = $session === null ? $r : $session->add($r);
}

echo str_repeat('─', 72) . PHP_EOL;
echo "Session summary:" . PHP_EOL;
printf("  Total cost:          %s%s", $session->format(), PHP_EOL);
printf("  Total input tokens:  %s%s", number_format($session->inputTokens()), PHP_EOL);
printf("  Total output tokens: %s%s", number_format($session->outputTokens()), PHP_EOL);
printf("  Cache write cost:    \$%.6f%s", $session->cacheWriteCostUsd(), PHP_EOL);
printf("  Cache read cost:     \$%.6f%s", $session->cacheReadCostUsd(), PHP_EOL);
printf("  Cache savings:       \$%.6f%s", $session->cacheSavingsUsd(), PHP_EOL);
echo PHP_EOL;

// ─── 5. JSON audit log entry ──────────────────────────────────────────────────
//
// A practical pattern for recording every API call in an audit trail.
// Build the log entry from the real usage object returned by Anthropic.

$anthropicUsage = [
    'input_tokens'                => 350,
    'output_tokens'               => 820,
    'cache_creation_input_tokens' => 4_800,
    'cache_read_input_tokens'     => 0,
];

$cost = PricingEngine::for('claude-sonnet-4-6')->calculate(
    inputTokens: $anthropicUsage['input_tokens'],
    outputTokens: $anthropicUsage['output_tokens'],
    cacheWriteTokens: $anthropicUsage['cache_creation_input_tokens'],
    cacheReadTokens: $anthropicUsage['cache_read_input_tokens'],
);

$auditEntry = array_merge(
    ['timestamp' => date('c'), 'request_id' => 'req_abc123'],
    $cost->toArray(),
    ['formatted' => $cost->format()],
);

echo "=== 5. Audit log entry (JSON) ===" . PHP_EOL;
echo json_encode($auditEntry, JSON_PRETTY_PRINT) . PHP_EOL;
echo PHP_EOL;

// ─── 6. MultimodalPricingResult — extended toArray() ─────────────────────────
//
// When estimateWithImages() is used the result includes extra fields:
//   image_tokens, image_cost_usd (merged into the standard toArray array)
// total_cost_usd is updated to include image costs.

$imageResult = PricingEngine::for('gpt-4o')->estimateWithImages(
    text: 'Analyse the defects visible in this engineering schematic.',
    images: [
        ImageAttachment::highDetail(1920, 1080),
        ImageAttachment::lowDetail(640, 480),
    ],
);

echo "=== 6. MultimodalPricingResult — toArray() with image fields ===" . PHP_EOL;
$imageData = $imageResult->toArray();
foreach ($imageData as $key => $value) {
    if (is_bool($value)) {
        printf("  %-28s %s%s", $key . ':', $value ? 'true' : 'false', PHP_EOL);
    } elseif (is_float($value)) {
        printf("  %-28s %.8f%s", $key . ':', $value, PHP_EOL);
    } else {
        printf("  %-28s %s%s", $key . ':', $value, PHP_EOL);
    }
}
echo PHP_EOL;
printf("  format():  %s%s", $imageResult->format(), PHP_EOL);
printf("  imageTokens():   %d%s", $imageResult->imageTokens(), PHP_EOL);
printf("  imageCostUsd():  \$%.6f%s", $imageResult->imageCostUsd(), PHP_EOL);
