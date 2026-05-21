# Pricing Result Types Reference

Once the `PricingEngine` executes successfully, it yields an immutable result DTO compliant via `PricingResultInterface`.

## Complete Methods Reference

### Cost Accessors (USD)

| Method | Return | Description |
|--------|--------|-------------|
| `inputCostUsd()` | `float` | Cost of standard (non-cached) input tokens. For OpenAI cached input, this already accounts for the subset split. |
| `outputCostUsd()` | `float` | Cost of completion output tokens. |
| `cacheWriteCostUsd()` | `float` | Surcharge for writing tokens to the Anthropic 5-minute prompt cache. Zero for OpenAI (no write surcharge). |
| `cacheReadCostUsd()` | `float` | Cost of reading from cache at the discounted rate (Anthropic or OpenAI). |
| `cacheSavingsUsd()` | `float` | How much was saved vs. paying the full input rate for all cached tokens. |
| `totalCostUsd()` | `float` | Sum of all cost components: `inputCostUsd + outputCostUsd + cacheWriteCostUsd + cacheReadCostUsd`. |

### Token Count Accessors

| Method | Return | Description |
|--------|--------|-------------|
| `inputTokens()` | `int` | Standard (non-cached) input tokens. |
| `outputTokens()` | `int` | Output / completion tokens. |
| `cacheWriteTokens()` | `int` | Tokens written to the Anthropic cache (surcharge applies). |
| `cacheReadTokens()` | `int` | Tokens read from cache (discounted). |

### Metadata

| Method | Return | Description |
|--------|--------|-------------|
| `model()` | `string` | The model identifier that produced this result. |
| `currency()` | `string` | Always `'USD'` in the built-in implementation. |
| `isUnknownModel()` | `bool` | `true` when the model was not found in any price table. Cost is `$0.00`. The engine never throws for unknown models. |
| `isZero()` | `bool` | `true` when `totalCostUsd()` is exactly `0.0` (unknown model or zero tokens). |

### Presentation

| Method | Return | Description |
|--------|--------|-------------|
| `format()` | `string` | Compact one-liner for logs. Example: `"$0.006500 USD (1,200 input + 350 output tokens)"` |
| `formatDetailed()` | `string` | Multi-line verbose breakdown including per-component costs and cache details. |

### Serialization

| Method | Return | Description |
|--------|--------|-------------|
| `toArray()` | `array` | All fields as a flat associative array. Keys: `model`, `currency`, `input_tokens`, `output_tokens`, `cache_write_tokens`, `cache_read_tokens`, `input_cost_usd`, `output_cost_usd`, `cache_write_cost_usd`, `cache_read_cost_usd`, `cache_savings_usd`, `total_cost_usd`, `is_unknown_model`. |

### Operations

| Method | Return | Description |
|--------|--------|-------------|
| `add(PricingResultInterface $other)` | `static` | Returns a **new** immutable instance with all token counts and costs summed. Safe to use in async workers (e.g. Laravel Horizon / Octane) because the originals are never modified. |

## Usage Examples

```php
$result = PricingEngine::for('gpt-4o')->calculate(
    inputTokens: 1_200,
    outputTokens: 350,
    cacheReadTokens: 1_000,  // OpenAI subset: 1000 of the 1200 were cached
);

echo $result->format();
// $0.005875 USD (1,200 input + 350 output tokens)

echo $result->formatDetailed();
// Full breakdown with cache details

echo $result->model();         // 'gpt-4o'
echo $result->currency();      // 'USD'
echo $result->inputTokens();   // 1200
echo $result->outputTokens();  // 350
echo $result->cacheSavingsUsd(); // savings vs full input rate

// Serialise for JSON storage or API transport
$data = $result->toArray();
echo json_encode($data);

// Accumulate costs across a tool-calling loop (immutable)
$session = $turn1->add($turn2)->add($turn3);
echo $session->totalCostUsd();
```

## Sub-Type: `MultimodalPricingResult`

Exclusively returned by `estimateWithImages()`. Extends `PricingResultInterface` with two additional methods:

| Method | Return | Description |
|--------|--------|-------------|
| `imageTokens()` | `int` | Total image token count across all images in the request. |
| `imageCostUsd()` | `float` | Cost of image tokens using the model's `imageInputPerMillion` rate (falls back to `inputPerMillion` if no explicit image rate). |

The standard `totalCostUsd()` already includes image costs. `inputTokens()` already includes image tokens. All `PricingResultInterface` methods delegate correctly.

```php
$result = PricingEngine::for('gpt-4o')->estimateWithImages(
    text: 'Describe this image.',
    images: [ImageAttachment::highDetail(1920, 1080)],
);

echo $result->imageTokens();   // e.g. 765 (from the tile formula)
echo $result->imageCostUsd();  // $0.001913
echo $result->format();
// "$0.002006 USD (8 text + 765 image + 0 output tokens)"
echo $result->totalCostUsd();  // text cost + image cost
```

`toArray()` on a `MultimodalPricingResult` includes two extra keys: `image_tokens` and `image_cost_usd`.

---

> **← Back:** [Pricing Engine Reference](pricing-engine.md) · **Next:** [Caching Strategies →](caching-strategies.md)
