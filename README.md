# nexus-ai-pricing

[![CI](https://github.com/token27/nexus-ai-pricing/actions/workflows/ci.yml/badge.svg)](https://github.com/token27/nexus-ai-pricing/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-Level%208-1f6feb)](https://phpstan.org/)
[![Latest Version](https://img.shields.io/packagist/v/token27/nexus-ai-pricing.svg?style=flat-square)](https://packagist.org/packages/token27/nexus-ai-pricing)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-72%20passing-brightgreen)](docs/testing.md)

An **extensively engineered, framework-agnostic** PHP 8.3+ financial calculation library precisely mapped for LLM API computations. Support inherently covers mathematically accurate pricing for OpenAI, Anthropic, Google Gemini, DeepSeek, Groq, Mistral, and Perplexity — resolving everything from Prompt Caching variants to multimodal image tiles locally.

## Why nexus-ai-pricing?

Calculating AI costs is chaotic. OpenAI utilizes a *subset caching* methodology where read tokens are subtracted from prompt tokens. Anthropic relies on *additive cache writes* with massive entry surcharges. Gemini alters limits based on *context tier tiers* and Perplexity adds *per-request bounds*.

**nexus-ai-pricing** solves this permanently by:

- **Mathematical Accuracy:** Handling all floating-point conversions safely without exception flaws.
- **Provider-agnostic core:** Evaluate cache, images, and text outputs using homogeneous DTOs (`PricingResult`).
- **Zero-Latency Estimations:** Utilizing [`token27/nexus-ai-tokenizer`](../nexus-ai-tokenizer) heavily to count everything strictly offline before issuing network requests.
- **Graceful Degradation:** Safely tags unknown models without triggering fatal system crashes during recursive loops.

## Features

- **Universal Support**: Standard metrics evaluated and natively bundled for ChatGPT, Claude, Gemini, DeepSeek Flash, and more.
- **Advanced Caching Mathematics**: Separating explicitly standard text costs from cached outputs seamlessly.
- **Vision Dimension Math**: Evaluates visual inputs correctly processing parameter rules dynamically (Tiles vs Dimensions).
- **Chained Price Tables**: Override built-in arrays securely via `JsonFilePriceTable` structures explicitly resolving priority overrides.
- **Glob Pattern Matcher**: Registers dynamic limits using wildcard variables (e.g. `gemini-2.1-*`) safely.
- **Type Safety**: PHPStan Level 8, completely production-grade testing limits.

## Installation

```bash
composer require token27/nexus-ai-pricing
```

**Requires:** PHP 8.3+ · [`token27/nexus-ai-tokenizer`](https://packagist.org/packages/token27/nexus-ai-tokenizer) `^1.0`

## Quick Start

### 1. Post-Request Billing calculation (Using API returned values)

```php
use Token27\NexusAI\Pricing\Engine\PricingEngine;

$result = PricingEngine::for('claude-sonnet-4-6')->calculate(
    inputTokens: 250,
    outputTokens: 400,
    cacheWriteTokens: 800,  // Anthropic 5-minute surcharge
    cacheReadTokens: 5000   // 90% discounted block
);

echo $result->totalCostUsd();     // Output standard metrics seamlessly
echo $result->cacheSavingsUsd();  // Output exactly what was saved against baselines
```

### 2. Pre-Request Proactive Estimations

```php
// Processes offline immediately avoiding Network HTTP limits
$estimated = PricingEngine::for('gpt-4o-mini')
    ->estimate('Generate a dense historical assessment regarding the Roman Empire.');

echo $estimated->format(); 
```

### 3. Multimodal Image Evaluation

```php
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;

// Handles internal tile mapping explicitly
$visionPrice = PricingEngine::for('gpt-4o')->estimateWithImages(
    text: 'Analyze the architectural defect visible here',
    images: [
        ImageAttachment::highDetail(1920, 1080)
    ]
);

echo $visionPrice->imageCostUsd();
```

### 4. Custom Image Estimators

All three pricing axes are independently injectable. For proprietary or custom vision models, pass your own `ImageTokenEstimatorInterface` implementations:

```php
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\Tokenizer\Contract\ImageTokenEstimatorInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\ValueObject\TokenCount;
use Token27\Tokenizer\Vision\AnthropicImageEstimator;
use Token27\Tokenizer\Vision\GeminiImageEstimator;
use Token27\Tokenizer\Vision\OpenAIImageEstimator;

// Proprietary model: flat 500 tokens per image regardless of size/detail
$myEstimator = new class implements ImageTokenEstimatorInterface {
    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        return new TokenCount(count: 500, model: $model, strategy: 'flat_500', approximate: false);
    }
    public function supports(string $model): bool
    {
        return str_starts_with($model, 'my-vision-');
    }
};

$engine = new PricingEngine(
    tokenizer:       TokenizerRegistry::createDefault(),
    priceTable:      new ArrayPriceTable([
        new ModelPrice('my-vision-model', inputPerMillion: 1.00, outputPerMillion: 4.00, imageInputPerMillion: 2.00),
    ]),
    imageEstimators: [
        $myEstimator,                  // my-vision-* → 500 tokens flat (checked first)
        new OpenAIImageEstimator(),    // gpt-4o, o1, o3, …  (built-in)
        new AnthropicImageEstimator(), // claude-*           (built-in)
        new GeminiImageEstimator(),    // gemini-*           (built-in)
    ],
);

$result = $engine->make('my-vision-model')->estimateWithImages(
    text:   'Analyse this diagram.',
    images: [ImageAttachment::highDetail(1920, 1080), ImageAttachment::lowDetail(800, 600)],
);

echo $result->imageTokens();   // 1000 (2 images × 500 flat)
echo $result->imageCostUsd();
```

Resolution: the first estimator whose `supports($model)` returns `true` wins. When `imageEstimators` is empty (the default), all three built-in estimators are used automatically.

## Documentation

- [Getting Started](docs/getting-started.md) — Cost calculations and quick one-liners
- [Installation Setup](docs/installation.md) — Composer, requirements, and DI container setup
- [Architecture Schematic](docs/architecture.md) — Core concepts, pricing engine flows and data structures
- [Pricing Engine Rules](docs/pricing-engine.md) — Class APIs, estimate methods, and facades
- [Pricing Result Typings](docs/pricing-result.md) — Returned metrics, DTOs, and standard formatting
- [Caching Strategies](docs/caching-strategies.md) — Anthropic Additive Logic vs OpenAI Subset bounds
- [Multimodal Vision Pricing](docs/multimodal-vision.md) — Image complexities, tiles, constraints, custom image estimators
- [Custom Price Tables](docs/custom-tables.md) — Decorating interfaces using external JSON arrays
- [Pricing Registry](docs/pricing-registry.md) — Factory instantiation, memory safety
- [Advanced Integration](docs/advanced-integration.md) — Immutable recursive iteration
- [Examples Guide](docs/examples.md) — Directory map against all 8 baseline examples
- [Default Internal Catalog](docs/default-catalog.md) — Complete parameter specifications
- [System Internals](docs/internals.md) — Math limits and unknown models
- [Testing & Validation](docs/testing.md) — PHPUnit, CS-Fixer and logic overrides
- [Contributing Guidelines](docs/contributing.md) — Safe repository upgrades

## License

MIT. See [LICENSE](LICENSE).
