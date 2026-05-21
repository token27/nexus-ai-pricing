# Installation & Setup

## Prerequisites

The **nexus-ai-pricing** library strictly enforces modern PHP code standards to guarantee stability in production environments:

- **PHP**: `^8.3`
- **Dependencies**: [`token27/nexus-ai-tokenizer`](https://packagist.org/packages/token27/nexus-ai-tokenizer) `^1.0` (which handles all underlying Tiktoken, SentencePiece, and BPE algorithms).

## Installing via Composer

Add the package to your project using Composer. This will automatically pull down theTokenizer library as well.

```bash
composer require token27/nexus-ai-pricing
```

### Local Development / Symlinking

If you are developing inside the `nexus-ai` ecosystem mono-repo or linking packages locally, use the local configuration:

```bash
COMPOSER=composer.local.json composer update
```

This forces Composer to symlink the `token27/nexus-ai-tokenizer` library directly from your filesystem (e.g. `../nexus-ai-tokenizer`) by utilizing an inline alias (`dev-main as 1.0.0`).

## Dependency Injection (DI) Container Setup

While static methods (`PricingEngine::for()`) are convenient, enterprise architectures should wire the engine into the DI container (e.g. Laravel, Symfony, PHP-DI).

```php
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\PriceTable\ChainedPriceTable;
use Token27\NexusAI\Pricing\PriceTable\JsonFilePriceTable;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;

return [
    PricingEngineInterface::class => function () {
        // Build a highly resilient chained table setup:
        $table = new ChainedPriceTable(
            // 1. First priority: Check your custom external JSON file (no redeploy needed)
            new JsonFilePriceTable('/etc/myapp/ai-prices.json'),
            // 2. Fallback: Use the default hardcoded catalog provided by the library
            new ArrayPriceTable(DefaultPriceCatalog::get()),
        );

        // Optionally pass a custom Tokenizer if you have one.
        return new PricingEngine(priceTable: $table);
    }
];
```

By binding `PricingEngineInterface`, any service can now calculate costs using `$this->pricing->calculate()`.

## Upgrading the Default Catalog

Because AI models change prices often, we recommend using a custom `JsonFilePriceTable` to manage your enterprise's prices.
Otherwise, update this library sequentially via Composer to fetch the new `DefaultPriceCatalog`.

```bash
composer update token27/nexus-ai-pricing
```

---

> **← Back:** [Getting Started](getting-started.md) · **Next:** [Architecture & Design Patterns →](architecture.md)
