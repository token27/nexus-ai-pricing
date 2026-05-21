<?php

/**
 * Example 04 — PricingRegistry
 *
 * PricingRegistry is the advanced alternative to ArrayPriceTable.
 * It adds lazy factory support and serves as a drop-in PriceTableInterface.
 *
 * Key differences vs ArrayPriceTable:
 *   - Stores Closure(): ModelPrice factories (loaded on first access)
 *   - Resolution cache prevents double-instantiation
 *   - createDefault() pre-populates from DefaultPriceCatalog with lazy factories
 *   - Mutable via register() / registerFactory() — useful for runtime customisation
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

// ─── 1. Default registry — mirrors DefaultPriceCatalog ────────────────────────

$registry = PricingRegistry::createDefault();

echo "=== Default registry ===" . PHP_EOL;
printf('Known model patterns: %d%s', count($registry->getKnownModels()), PHP_EOL);
printf('Has gpt-4o:              %s%s', $registry->hasPrice('gpt-4o') ? 'yes' : 'no', PHP_EOL);
printf('Has claude-sonnet-4-6:   %s%s', $registry->hasPrice('claude-sonnet-4-6') ? 'yes' : 'no', PHP_EOL);
printf('Has nonexistent-model:   %s%s', $registry->hasPrice('nonexistent-model') ? 'yes' : 'no', PHP_EOL);
echo PHP_EOL;

// ─── 2. Glob pattern resolution ───────────────────────────────────────────────
//
// Patterns like 'claude-haiku-4*' registered in the catalog resolve any
// concrete model ID that matches via fnmatch(). The longest matching pattern wins.

$price = $registry->getPrice('claude-haiku-4-5-20251001');
echo "=== Glob resolution ===" . PHP_EOL;
printf('Resolved model:  %s%s', $price->model, PHP_EOL);
printf('Input /M:        $%.2f%s', $price->inputPerMillion, PHP_EOL);
printf('Output /M:       $%.2f%s', $price->outputPerMillion, PHP_EOL);
echo PHP_EOL;

// ─── 3. Extending the default registry ───────────────────────────────────────
//
// start from the full catalog, then override or add entries.
// register() is the eager variant (stores value immediately).
// registerFactory() is the lazy variant (closure called on first access).

$extended = PricingRegistry::createDefault();

// Eager override: replace gpt-4o with a negotiated rate
$extended->register(new ModelPrice(
    model: 'gpt-4o',
    inputPerMillion: 2.00,
    outputPerMillion: 8.00,
    notes: 'Enterprise rate',
));

// Lazy factory: the closure is only called when 'my-batch-llm' is first requested
$extended->registerFactory('my-batch-llm', static function (): ModelPrice {
    // In a real app this might load from a database or config file
    return new ModelPrice(
        model: 'my-batch-llm',
        inputPerMillion: 0.20,
        outputPerMillion: 0.80,
        notes: 'Batch-optimized endpoint',
    );
});

// Glob pattern factory: covers my-llm-v1, my-llm-v2, my-llm-pro, …
$extended->registerFactory('my-llm-*', static function (): ModelPrice {
    return new ModelPrice(
        model: 'my-llm-*',
        inputPerMillion: 1.00,
        outputPerMillion: 4.00,
        notes: 'Internal LLM family — all variants',
    );
});

echo "=== Extended registry ===" . PHP_EOL;

$gpt4oPrice    = $extended->getPrice('gpt-4o');
$batchPrice    = $extended->getPrice('my-batch-llm');
$myLlmV2Price  = $extended->getPrice('my-llm-v2');
$myLlmProPrice = $extended->getPrice('my-llm-pro');

printf('gpt-4o (overridden):   $%.2f/$%.2f per M%s', $gpt4oPrice->inputPerMillion, $gpt4oPrice->outputPerMillion, PHP_EOL);
printf('my-batch-llm (lazy):   $%.2f/$%.2f per M%s', $batchPrice->inputPerMillion, $batchPrice->outputPerMillion, PHP_EOL);
printf('my-llm-v2 (glob):      $%.2f/$%.2f per M | model=%s%s', $myLlmV2Price->inputPerMillion, $myLlmV2Price->outputPerMillion, $myLlmV2Price->model, PHP_EOL);
printf('my-llm-pro (glob):     $%.2f/$%.2f per M | model=%s%s', $myLlmProPrice->inputPerMillion, $myLlmProPrice->outputPerMillion, $myLlmProPrice->model, PHP_EOL);
echo PHP_EOL;

// ─── 4. Registry as PricingEngine backend ─────────────────────────────────────
//
// Pass the registry to PricingEngine::withRegistry() and all model lookups
// go through your registry instead of the default catalog.

$engine = PricingEngine::withRegistry($extended);

$r1 = $engine->make('gpt-4o')->calculate(inputTokens: 1_000, outputTokens: 500);
$r2 = $engine->make('my-batch-llm')->calculate(inputTokens: 1_000, outputTokens: 500);
$r3 = $engine->make('my-llm-v2')->calculate(inputTokens: 1_000, outputTokens: 500);

echo "=== PricingEngine with extended registry ===" . PHP_EOL;
printf('gpt-4o (1k+500):      %s%s', $r1->format(), PHP_EOL);
printf('my-batch-llm (1k+500): %s%s', $r2->format(), PHP_EOL);
printf('my-llm-v2 (1k+500):   %s%s', $r3->format(), PHP_EOL);
echo PHP_EOL;

// ─── 5. Unknown model resolution — never throws ───────────────────────────────

$unknown = $engine->make('completely-unknown-model-xyz')->calculate(inputTokens: 500, outputTokens: 100);

echo "=== Unknown model fallback ===" . PHP_EOL;
printf('Is unknown model:  %s%s', $unknown->isUnknownModel() ? 'yes' : 'no', PHP_EOL);
printf('Total cost:        $%.6f (always zero for unknown)%s', $unknown->totalCostUsd(), PHP_EOL);
printf('Model:             %s%s', $unknown->model(), PHP_EOL);
echo PHP_EOL;

// ─── 6. Inspect resolved price before making a request ────────────────────────

$builder = $engine->make('claude-sonnet-4-6');
$price   = $builder->getPrice();

echo "=== Inspect price before sending ===" . PHP_EOL;
printf('Model:                 %s%s', $price->model, PHP_EOL);
printf('Input /M:              $%.2f%s', $price->inputPerMillion, PHP_EOL);
printf('Output /M:             $%.2f%s', $price->outputPerMillion, PHP_EOL);
printf('Supports caching:      %s%s', $price->supportsCache() ? 'yes' : 'no', PHP_EOL);
printf('Cache write /M:        $%.4f%s', $price->cacheWritePerMillion ?? 0.0, PHP_EOL);
printf('Cache read /M:         $%.4f%s', $price->cacheReadPerMillion ?? 0.0, PHP_EOL);
printf('Cache is input subset: %s%s', $price->cacheReadIsSubsetOfInput ? 'yes (OpenAI-style)' : 'no (Anthropic-style)', PHP_EOL);
