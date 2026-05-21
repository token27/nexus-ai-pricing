<?php

/**
 * Example 01 — Quick Start
 *
 * The simplest possible usage: zero-config one-liners for the most common operations.
 * No DI container, no configuration, no constructor calls.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Engine\PricingEngine;

// ─── Post-request: cost from real API usage ───────────────────────────────────
//
// You get these token counts from the LLM API response.
// For OpenAI: usage.prompt_tokens, usage.completion_tokens
// For Anthropic: usage.input_tokens, usage.output_tokens

$result = PricingEngine::for('gpt-4o')
    ->calculate(inputTokens: 1_200, outputTokens: 350);

echo "=== Post-request cost ===" . PHP_EOL;
echo $result->format() . PHP_EOL;
// $0.008500 USD (1,200 input + 350 output tokens)
echo 'Input cost:  $' . number_format($result->inputCostUsd(), 6) . PHP_EOL;
// Input cost:  $0.003000
echo 'Output cost: $' . number_format($result->outputCostUsd(), 6) . PHP_EOL;
// Output cost: $0.003500
echo 'Total:       $' . number_format($result->totalCostUsd(), 6) . PHP_EOL;
// Total:       $0.008500
echo PHP_EOL;

// ─── Pre-request: estimate cost before sending ───────────────────────────────
//
// Counts tokens internally via nexus-ai-tokenizer and applies the model price.
// Useful for budget checks before making the API call.

$estimated = PricingEngine::for('claude-sonnet-4-6')
    ->estimate('Write a comprehensive PHP tutorial covering arrays, closures, and generators.');

echo "=== Pre-request estimate ===" . PHP_EOL;
echo $estimated->format() . PHP_EOL;
// $0.000... USD (N input + 0 output tokens)
echo 'Token count: ' . $estimated->inputTokens() . PHP_EOL;
echo PHP_EOL;

// ─── Chat conversation estimate ──────────────────────────────────────────────
//
// Counts tokens with provider-specific overhead (ChatML markers, role tokens, etc.)

$chatResult = PricingEngine::for('gpt-4o')->estimateChat([
    ['role' => 'system', 'content' => 'You are a senior PHP developer. Be concise and precise.'],
    ['role' => 'user', 'content' => 'Explain the difference between abstract classes and interfaces in PHP 8.3.'],
]);

echo "=== Chat estimate ===" . PHP_EOL;
echo $chatResult->format() . PHP_EOL;
echo 'Includes ChatML overhead: yes (3 tokens/msg + 3 priming tokens)' . PHP_EOL;
echo PHP_EOL;

// ─── Any provider, same API ───────────────────────────────────────────────────

foreach (['gpt-4o', 'claude-sonnet-4-6', 'gemini-2.5-flash', 'deepseek-v4-flash'] as $model) {
    $r = PricingEngine::for($model)->calculate(inputTokens: 1_000, outputTokens: 500);
    printf("%-30s %s%s", $model . ':', $r->format(), PHP_EOL);
}
