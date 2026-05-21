# Pricing Engine Reference

The `PricingEngine` class fundamentally isolates consumer systems from Tokenizer complexity and Provider-specific mapping logic restrictions.

## Static Facade vs Factory Method

```php
// Approach 1: Zero configuration default instantiation. 
// Leverages `DefaultPriceCatalog` and `TokenizerRegistry::createDefault()` silently.
$result = PricingEngine::for('gpt-4o')->calculate(100, 50);

// Approach 2: Granular enterprise instance explicitly assigning the exact tables required.
use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;

$engine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter($myTokenizerInstance),
    priceTable: $myCustomJsonTables
);
$result = $engine->calculate(model: 'gpt-4o', inputTokens: 100, outputTokens: 50);
```

## Three Injectable Axes

`PricingEngine` has three independently injectable components. Each has a sensible default so you only provide what you need to customize:

| Axis | Constructor param | Default |
|---|---|---|
| Text tokenizer | `textEstimator: TextEstimatorInterface\|null` | `null` — `estimate()` not available without this |
| Price table | `priceTable: PriceTableInterface` | `new ArrayPriceTable([])` — use `PricingRegistry::createDefault()` or `DefaultPriceCatalog::get()` in practice |
| Image estimators | `imageEstimators: list<ImageTokenEstimatorInterface>` | `[]` — auto-loads built-in estimators from `nexus-ai-tokenizer` if installed |

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

// Custom image estimator for a proprietary model
$myEstimator = new class implements ImageTokenEstimatorInterface {
    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        return new TokenCount(count: 500, model: $model, strategy: 'flat_rate', approximate: false);
    }
    public function supports(string $model): bool
    {
        return str_starts_with($model, 'my-vision-');
    }
};

$engine = new PricingEngine(
    tokenizer:       TokenizerRegistry::createDefault(),       // Axis 1: text tokens
    priceTable:      new ArrayPriceTable([                     // Axis 2: prices
        new ModelPrice('my-vision-model', inputPerMillion: 1.00, outputPerMillion: 4.00, imageInputPerMillion: 2.00),
    ]),
    imageEstimators: [                                         // Axis 3: image tokens
        $myEstimator,                  // my-vision-* → 500 tokens flat
        new OpenAIImageEstimator(),    // gpt-4o, o1, o3, …
        new AnthropicImageEstimator(), // claude-*
        new GeminiImageEstimator(),    // gemini-*
    ],
);
```

### Image Estimator Resolution Order

1. Iterates the `imageEstimators` list in order.
2. First estimator whose `supports($model)` returns `true` is used.
3. If none matches, falls back to `OpenAIImageEstimator` (conservative, tile-based).

When `imageEstimators` is empty (the default), the three built-in estimators are used automatically — you don't need to pass them unless you want to add a custom one.

See `examples/10_custom_image_estimators.php` for the full reference of all custom estimator patterns.

## Calculation Variations

1. `->calculate(int $inputTokens, int $outputTokens, int $cacheWriteTokens = 0, int $cacheReadTokens = 0)`
Strict mathematical mapping post-execution. Takes values delivered directly via external API responses.

2. `->estimate(string $text)`
Delegates parsing natively to the tokenizer libraries and instantly provides proactive cost assessments.

3. `->estimateChat(array $messages)`
Translates typical ChatML structures (like `[['role' => 'user', 'content' => 'Hello!']]`), factoring in provider priming message taxes and base sequence padding overrides explicitly.

4. `->estimateWithImages(string $text, array $images)`
Transforms binary structure data into grid dimensions seamlessly depending on target algorithms before passing to multimodal result mappers.

> **Note:** `estimate()`, `estimateChat()`, and `estimateWithImages()` all require a `TextEstimatorInterface` to be injected. Use `PricingEngine::withTokenizer($registry)` or inject `textEstimator: new TokenizerBridgeAdapter($registry)` via the constructor. Calling these methods without a text estimator throws `EstimationNotAvailableException`.

---

> **← Back:** [Architecture & Design Patterns](architecture.md) · **Next:** [Pricing Result Types →](pricing-result.md)
