# Caching & Cost Reduction Strategies

Prompt Caching represents a paradigm shift in how we pay for LLM inferences. However, there is a **fundamental difference in math and calculation logic** between the top two providers (Anthropic and OpenAI). If your math incorrectly evaluates 'additive' paths versus 'subset' paths, you will wildly miscalculate costs.

This library natively resolves these differences.

## The Architectural Divide

```mermaid
graph TD
    subgraph Anthropic Caching (Additive Logic)
    A[Input Tokens: 200] --> B(Standard Cost)
    C[Cache Write: 800] --> D(1.25x Cost)
    E[Cache Read: 5000] --> F(0.10x Cost)
    B --> Total[Calculate Separate Components]
    D --> Total
    F --> Total
    end

    subgraph OpenAI Caching (Subset Logic)
    X[Prompt Tokens: 5200] --> Y(Base Cost: Calculate 5200 - 5000)
    Z[Cached Tokens: 5000 *subset of Prompt*] --> W(0.50x Cost)
    Y --> TotalOpenAI[Sum Discounted and Base]
    W --> TotalOpenAI
    end
```

## 1. Anthropic Prompt Caching

Anthropic treats its cache explicitly as separate metrics. You pay a surcharge initially to place tokens into memory, and you pay a huge discount whenever that memory is read.

### API Response Mapping

When you get a response from the API, the object looks like this:

- `usage.input_tokens` (non-cached standard tokens)
- `usage.cache_creation_input_tokens` (surcharge write tokens)
- `usage.cache_read_input_tokens` (discounted read tokens)

### Implementation

```php
$result = PricingEngine::for('claude-sonnet-4-6')->calculate(
    inputTokens:      200,      // standard input
    outputTokens:     350,      // output tokens
    cacheWriteTokens: 800,      // written to 5-minute cache (surcharge)
    cacheReadTokens:  5_000,    // read from cache (90% discount)
);

echo $result->cacheSavingsUsd(); // $0.013500 saved compared to paying full standard text price.
```

**Break-even Insight:** Because the cache write surcharge is `1.25x`, and the read discount is `0.10x`, you mathematically **break even** on your first subsequent read request. Every request after the first is saving you an immense amount of money.

## 2. OpenAI Cached Input Tokens

OpenAI handles input caching opaquely. There is no cache write surcharge. If the prefix of your prompt sequence explicitly matches a sequence they've seen recently, you get an automatic discount.

**Crucially:** the `prompt_tokens` field provided by OpenAI includes the cached amount entirely inside it.

### API Response Mapping

- `usage.prompt_tokens` (ALL tokens, including cached tokens).
- `usage.prompt_tokens_details.cached_tokens` (The subset of prompt_tokens that received the discount).

### Implementation

```php
$result = PricingEngine::for('gpt-4o')->calculate(
    inputTokens:     5_200,  // The FULL amount
    outputTokens:      400,
    cacheReadTokens: 5_000,  // Subset flag
);

// The Engine automatically understands that OpenAI uses the subset logic for cache checks.
// It will bill (5_200 - 5_000) at the standard rate, and 5_000 at the discounted rate.
```

### How `ModelPrice` handles this internally

Every `ModelPrice` instance includes a boolean flag: `$cacheReadIsSubsetOfInput`.

- If `true` (OpenAI), the engine subtracts the read tokens from the input tokens to find standard cost.
- If `false` (Anthropic), the engine assumes input tokens are distinct from read tokens.

---

> **← Back:** [Pricing Result Types](pricing-result.md) · **Next:** [Vision & Multimodal Pricing →](multimodal-vision.md)
