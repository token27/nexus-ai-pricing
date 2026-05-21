<?php

/**
 * Example 03 — Custom Price Tables
 *
 * Shows three ways to override or extend the default catalog:
 *
 *   1. ArrayPriceTable — in-memory, supports glob patterns
 *   2. JsonFilePriceTable — lazy-loaded from a JSON file, supports globs
 *   3. ChainedPriceTable — priority delegation (custom overrides → default catalog)
 *   4. Runtime registerPrice() — add/override prices on a live engine instance
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\PriceTable\ChainedPriceTable;
use Token27\NexusAI\Pricing\PriceTable\JsonFilePriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

// ─── 1. ArrayPriceTable — in-memory overrides ─────────────────────────────────
//
// Useful when you need to tweak a few prices without touching the default catalog.
// Glob patterns let you cover entire model families with one entry.

$customTable = new ArrayPriceTable([
    // Override gpt-4o with your negotiated enterprise rate
    new ModelPrice(
        model: 'gpt-4o',
        inputPerMillion: 2.00,  // normally $2.50/M
        outputPerMillion: 8.00,  // normally $10.00/M
        notes: 'Enterprise agreement #EA-2026-0042 — valid until 2026-12-31',
    ),
    // Glob pattern: catch any future gpt-5 variant with a single entry
    new ModelPrice(
        model: 'gpt-5*',
        inputPerMillion: 5.00,
        outputPerMillion: 20.00,
        notes: 'Estimated — update when gpt-5 pricing is published',
    ),
    // Private internal model
    new ModelPrice(
        model: 'my-internal-llm',
        inputPerMillion: 0.50,
        outputPerMillion: 2.00,
        cacheWritePerMillion: 0.625,
        cacheReadPerMillion: 0.05,
        notes: 'Internal GPU cluster — infra cost estimate',
    ),
]);

$engine = PricingEngine::withTable($customTable);

$gpt4oResult = $engine->make('gpt-4o')->calculate(inputTokens: 1_000, outputTokens: 500);
echo "=== ArrayPriceTable — negotiated gpt-4o rate ===" . PHP_EOL;
echo $gpt4oResult->format() . PHP_EOL;
printf('Input cost:  $%.6f (@ $2.00/M)%s', $gpt4oResult->inputCostUsd(), PHP_EOL);
printf('Output cost: $%.6f (@ $8.00/M)%s', $gpt4oResult->outputCostUsd(), PHP_EOL);
echo PHP_EOL;

// Glob match: any 'gpt-5-turbo', 'gpt-5-mini', etc. resolves to the gpt-5* entry
$gpt5Result = $engine->make('gpt-5-turbo')->calculate(inputTokens: 500, outputTokens: 200);
echo "=== Glob match: gpt-5-turbo → gpt-5* pattern ===" . PHP_EOL;
echo $gpt5Result->format() . PHP_EOL;
echo PHP_EOL;

// ─── 2. JsonFilePriceTable — prices from a JSON file ─────────────────────────
//
// The file is loaded lazily on first access and cached for subsequent calls.
// See tests/fixtures/custom-prices.json for the expected format.

$jsonFixture = dirname(__DIR__) . '/tests/fixtures/custom-prices.json';
$jsonTable   = new JsonFilePriceTable($jsonFixture);

$privateResult = PricingEngine::withTable($jsonTable)
    ->make('my-private-llm')
    ->calculate(
        inputTokens: 300,
        outputTokens: 150,
        cacheWriteTokens: 2_000,
        cacheReadTokens: 0,
    );

echo "=== JsonFilePriceTable — my-private-llm ===" . PHP_EOL;
echo $privateResult->formatDetailed() . PHP_EOL;
echo PHP_EOL;

// Glob pattern from the JSON file: 'enterprise-model-*' covers enterprise-model-v1, v2, etc.
$enterpriseResult = PricingEngine::withTable($jsonTable)
    ->make('enterprise-model-v2')
    ->calculate(
        inputTokens: 1_000,
        outputTokens: 400,
        cacheReadTokens: 2_000,  // OpenAI-style: subset of prompt_tokens
    );

echo "=== Glob from JSON: enterprise-model-v2 → enterprise-model-* ===" . PHP_EOL;
echo $enterpriseResult->formatDetailed() . PHP_EOL;
printf('Cache savings: $%.6f%s', $enterpriseResult->cacheSavingsUsd(), PHP_EOL);
echo PHP_EOL;

// ─── 3. ChainedPriceTable — priority delegation ───────────────────────────────
//
// First table in the chain has highest priority.
// Unknown models fall through to the next table.
//
//   custom overrides → default catalog
//
// This is the production pattern: custom file overrides the built-in catalog,
// so you never need to fork the catalog just to change one model's price.

$overrideTable = new ArrayPriceTable([
    new ModelPrice(
        model: 'gpt-4o',
        inputPerMillion: 1.80,  // deep discount, negotiated rate
        outputPerMillion: 7.50,
        notes: 'Custom enterprise rate',
    ),
]);

$chain = new ChainedPriceTable(
    $overrideTable,                                   // highest priority
    new ArrayPriceTable(DefaultPriceCatalog::get()),  // fallback: full catalog
);

$chainEngine = PricingEngine::withTable($chain);

echo "=== ChainedPriceTable ===" . PHP_EOL;

// gpt-4o → resolved from override table ($1.80/$7.50)
$overriddenResult = $chainEngine->make('gpt-4o')->calculate(inputTokens: 1_000, outputTokens: 500);
printf('gpt-4o (overridden):          %s%s', $overriddenResult->format(), PHP_EOL);

// claude-sonnet-4-6 → not in override, falls through to default catalog
$fallbackResult = $chainEngine->make('claude-sonnet-4-6')->calculate(inputTokens: 1_000, outputTokens: 500);
printf('claude-sonnet-4-6 (catalog):  %s%s', $fallbackResult->format(), PHP_EOL);
echo PHP_EOL;

// ChainedPriceTable is immutable — prepend/append return new instances
$withJsonFirst = (new ChainedPriceTable(new ArrayPriceTable(DefaultPriceCatalog::get())))
    ->prepend($jsonTable);  // JSON file now has highest priority

echo "=== Prepend JSON table to existing chain ===" . PHP_EOL;
printf('Known models in chain: %d%s', count($withJsonFirst->getKnownModels()), PHP_EOL);
echo PHP_EOL;

// ─── 4. Runtime registerPrice() — add prices on a live engine ────────────────
//
// Use PricingEngine::for() for zero-config access and call registerPrice()
// to add or override a price on the fly without rebuilding the engine.

$liveEngine = PricingEngine::withTable(new ArrayPriceTable(DefaultPriceCatalog::get()));

// Before: unknown model → zero cost, isUnknownModel() = true
$beforeResult = $liveEngine->make('my-new-model')->calculate(inputTokens: 100, outputTokens: 50);
printf('Before registration — unknown: %s, cost: $%.6f%s', $beforeResult->isUnknownModel() ? 'yes' : 'no', $beforeResult->totalCostUsd(), PHP_EOL);

// Register the new model's price at runtime
$liveEngine->registerPrice(new ModelPrice(
    model: 'my-new-model',
    inputPerMillion: 1.00,
    outputPerMillion: 4.00,
    notes: 'Registered at runtime',
));

// After: resolved correctly
$afterResult = $liveEngine->make('my-new-model')->calculate(inputTokens: 100, outputTokens: 50);
printf('After registration  — unknown: %s, cost: $%.6f%s', $afterResult->isUnknownModel() ? 'yes' : 'no', $afterResult->totalCostUsd(), PHP_EOL);
printf('Model price: input $%.2f/M, output $%.2f/M%s', $liveEngine->getPriceFor('my-new-model')->inputPerMillion, $liveEngine->getPriceFor('my-new-model')->outputPerMillion, PHP_EOL);
