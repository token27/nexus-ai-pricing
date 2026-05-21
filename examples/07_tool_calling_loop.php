<?php

/**
 * Example 07 — Tool-Calling Loop Cost Accumulation
 *
 * A realistic agentic workflow where the model is called multiple times across
 * several turns: initial reasoning, tool calls, and a final synthesis step.
 *
 * PricingResult::add() combines results immutably — sum as many turns as needed.
 *
 * Pattern:
 *   $total = $turn1->add($turn2)->add($turn3)->add($turn4);
 *   echo $total->totalCostUsd(); // cumulative cost for the whole run
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\Engine\PricingEngine;

$model = 'claude-sonnet-4-6';

// ─── Scenario: web-research agent ─────────────────────────────────────────────
//
// Turn 1 — initial user request + system prompt
//   The large system prompt is written to the 5-min prompt cache.
//
// Turn 2 — model decides to call three tools (web_search, web_fetch, code_exec)
//   System prompt is now served from cache.
//
// Turn 3 — tool results returned, model processes them
//   System prompt still cached.
//
// Turn 4 — model writes final report
//   System prompt cached. Long output.

$systemPromptTokens = 4_800;  // system prompt, written to cache on turn 1

echo "=== Web-research agent — 4-turn tool-calling loop ===" . PHP_EOL;
echo str_repeat('─', 70) . PHP_EOL;

// Turn 1: cache WRITE (initial request)
$turn1 = PricingEngine::for($model)->calculate(
    inputTokens: 350,                 // user message + small context
    outputTokens: 280,                 // model's plan / first tool call
    cacheWriteTokens: $systemPromptTokens, // 4,800 tokens written to 5-min cache
    cacheReadTokens: 0,
);
printf('Turn 1 (cache WRITE):  %s%s', $turn1->formatDetailed(), PHP_EOL);

// Turn 2: cache READ — three tool calls returned as part of the conversation
$turn2 = PricingEngine::for($model)->calculate(
    inputTokens: 850,                  // previous output + tool results
    outputTokens: 420,                  // model synthesising, calling more tools
    cacheReadTokens: $systemPromptTokens,  // 4,800 tokens served from cache
);
printf('Turn 2 (cache READ):   %s%s', $turn2->formatDetailed(), PHP_EOL);

// Turn 3: cache READ — more tool results processed
$turn3 = PricingEngine::for($model)->calculate(
    inputTokens: 1_200,
    outputTokens: 380,
    cacheReadTokens: $systemPromptTokens,
);
printf('Turn 3 (cache READ):   %s%s', $turn3->formatDetailed(), PHP_EOL);

// Turn 4: cache READ — final synthesis (longer output)
$turn4 = PricingEngine::for($model)->calculate(
    inputTokens: 1_500,
    outputTokens: 1_800,                // long final report
    cacheReadTokens: $systemPromptTokens,
);
printf('Turn 4 (cache READ):   %s%s', $turn4->formatDetailed(), PHP_EOL);

echo str_repeat('─', 70) . PHP_EOL;

// add() is immutable — each call returns a new PricingResult
$session = $turn1->add($turn2)->add($turn3)->add($turn4);

printf('Session total:         %s%s', $session->formatDetailed(), PHP_EOL);
echo PHP_EOL;
printf('Total cost:            $%.6f%s', $session->totalCostUsd(), PHP_EOL);
printf('Total cache savings:   $%.6f%s', $session->cacheSavingsUsd(), PHP_EOL);
printf('Cache write cost:      $%.6f%s', $session->cacheWriteCostUsd(), PHP_EOL);
printf('Cache read  cost:      $%.6f%s', $session->cacheReadCostUsd(), PHP_EOL);

// Without caching, all input tokens would have been billed at standard rate
$totalInputWithoutCache = $turn1->inputTokens() + $systemPromptTokens
    + $turn2->inputTokens() + $systemPromptTokens
    + $turn3->inputTokens() + $systemPromptTokens
    + $turn4->inputTokens() + $systemPromptTokens;

$worstCase = PricingEngine::for($model)->calculate(
    inputTokens: $totalInputWithoutCache,
    outputTokens: $session->outputTokens(),
);

printf('%s', PHP_EOL);
printf('Without caching (worst case): $%.6f%s', $worstCase->totalCostUsd(), PHP_EOL);
printf('With caching (actual):        $%.6f%s', $session->totalCostUsd(), PHP_EOL);
printf(
    'Net saving vs worst case:     $%.6f (%.1f%%)%s',
    $worstCase->totalCostUsd() - $session->totalCostUsd(),
    (($worstCase->totalCostUsd() - $session->totalCostUsd()) / $worstCase->totalCostUsd()) * 100,
    PHP_EOL,
);
echo PHP_EOL;

// ─── Pattern: accumulate inside a loop ────────────────────────────────────────
//
// Useful when the number of turns is dynamic (agent decides when to stop).

echo "=== Dynamic accumulation pattern ===" . PHP_EOL;

/**
 * Simulate one agent step and return its pricing result.
 *
 * In a real app this would be: call LLM → get usage → calculate().
 */
function simulateStep(string $model, int $step): PricingResultInterface
{
    return PricingEngine::for($model)->calculate(
        inputTokens: 400 + $step * 100,
        outputTokens: 200 + $step * 50,
        cacheReadTokens: 3_000,
    );
}

$runTotal = null;
$steps    = 5;

for ($i = 1; $i <= $steps; $i++) {
    $step = simulateStep($model, $i);
    printf('Step %d: %s%s', $i, $step->format(), PHP_EOL);

    $runTotal = $runTotal === null ? $step : $runTotal->add($step);
}

echo PHP_EOL;
printf('Run total (%d steps): $%.6f%s', $steps, $runTotal->totalCostUsd(), PHP_EOL);
printf('Total tokens: %d input + %d output%s', $runTotal->inputTokens(), $runTotal->outputTokens(), PHP_EOL);
printf('Total saved:  $%.6f from prompt cache%s', $runTotal->cacheSavingsUsd(), PHP_EOL);
echo PHP_EOL;

// ─── Budget guard — stop when cost exceeds threshold ──────────────────────────

echo "=== Budget guard ===" . PHP_EOL;

$budget     = 0.05;  // $0.05 hard cap
$accumulated = null;
$stepCount  = 0;

for ($i = 1; $i <= 20; $i++) {
    $step = simulateStep($model, $i);
    $next = $accumulated === null ? $step : $accumulated->add($step);

    if ($next->totalCostUsd() > $budget) {
        printf('Budget cap hit at step %d — projected $%.6f > limit $%.6f%s', $i, $next->totalCostUsd(), $budget, PHP_EOL);
        break;
    }

    $accumulated = $next;
    $stepCount   = $i;
}

if ($accumulated !== null) {
    printf('Completed %d step(s), spent $%.6f%s', $stepCount, $accumulated->totalCostUsd(), PHP_EOL);
}
