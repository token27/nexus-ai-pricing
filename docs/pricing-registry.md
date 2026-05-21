# Pricing Registry & Lazy Resolution

The `PricingRegistry` acts as an advanced container for resolving system configurations securely and efficiently. Instead of pre-instantiating hundreds of ValueObject elements globally on system boot, it supports Lazy Callbacks and Regex-like Glob pattern definitions.

## Lazy Instantiations (The Factory Pattern)

When loading external configuration states (e.g. from a Database mapping dynamic costs for endpoints), loading them directly stalls booting. `PricingRegistry` solves this through `.registerFactory()`.

```php
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

$registry = PricingRegistry::createDefault();

$registry->registerFactory('special-local-batch-llm', static function (): ModelPrice {
    // This closure ONLY executes when the user actively requests an estimate for 'special-local-batch-llm'.
    // Memory overhead is functionally 0mb until requested.
    return new ModelPrice(
        model: 'special-local-batch-llm',
        inputPerMillion: 0.10,
        outputPerMillion: 0.20,
    );
});
```

## Glob Pattern Matching

Providers often inject metadata into model endpoints without altering prices (e.g., `gemini-2.5-pro-preview-0409`, `claude-haiku-4-5-20251001`). Rather than updating the database to map exact names daily, use the intrinsic `.hasPrice()` wildcard mechanics.

```php
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

$registry = PricingRegistry::createDefault();

$registry->registerFactory('enterprise-internal-*', static function(): ModelPrice {
    return new ModelPrice(
        model: 'enterprise-internal-*',
        inputPerMillion: 1.00,
        outputPerMillion: 2.50,
        notes: "Glob matching resolving variant specific enterprise internal branches"
    );
});

// A system calls calculating on `enterprise-internal-qa-v2`.
// The registry checks exactly, fails, parses wildcards, matches, and successfully resolves.
```

## Binding to the Engine

To deploy an enhanced customized registry over normal price tables, inject it directly into the `PricingEngine`.

```php
use Token27\NexusAI\Pricing\Engine\PricingEngine;

$customRegistry = PricingRegistry::createDefault();
// ... (Add adjustments)

$engine = PricingEngine::withRegistry($customRegistry);

// Calculates using injected dependencies.
$engine->calculate('enterprise-internal-qa-v2', 500, 500);
```

---

> **← Back:** [Custom Price Tables](custom-tables.md) · **Next:** [Default Catalog →](default-catalog.md)
