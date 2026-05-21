# Troubleshooting

When manipulating prices, configuring enterprise tables or evaluating tokenization streams, some architectural hurdles might naturally present themselves.

## The Model Fails Formatting Properly (Missing Cost Metric)

If `totalCostUsd` routinely outputs zero where expenses distinctly occurred:

- **Verify Registry Link**: Did you correctly implement `ChainedPriceTable` ensuring Fallback references were structurally included?
- **Verify Error Logging**: Did you test if `isUnknownModel()` returned true? If true, the system is gracefully stopping calculations due to missing mappings. Use the Factory override mechanism or manually inject it into the local `ai-prices.json`.

## Math Mismatch / Floating Point Restrictions

When outputting terminal UI formatting, do not interact via basic concatenation (e.g. `$variable . " USD"`). Utilize the native `.format()` utility methods directly provided.
Internal logic safely isolates variables dividing via `1,000,000` prior to modification maintaining total integrity across metrics exceeding standard integers.

## File Permissions Fault `JsonFilePriceTable`

If an exception inherently interrupts system execution via `PriceTableException`, your process does not intrinsically own access to the decoupled external JSON schema mappings.

- Ensure system runners (e.g. `www-data`) possess reading allowances targeting that directory constraint.
- Ensure the schema itself lacks trailing commas and correctly matches definitions (No misspelled metrics).

## `EstimationNotAvailableException` when calling `estimate()`

`estimate()`, `estimateChat()`, and `estimateWithImages()` all require a text tokenizer. The zero-config `PricingEngine::for()` does **not** include one by default (it only supports `calculate()`).

**Fix:** Use `PricingEngine::withTokenizer(TokenizerRegistry::createDefault())` or inject `textEstimator: new TokenizerBridgeAdapter($registry)` via the constructor.

---

> **← Back:** [Examples Guide](examples.md) · **Next:** [Testing →](testing.md)
