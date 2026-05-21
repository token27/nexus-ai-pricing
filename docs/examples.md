# Examples Guide

The `nexus-ai-pricing` library includes a comprehensive `/examples/` folder with 13 runnable scripts covering all major usage scenarios — from zero-config one-liners to full enterprise DI wiring.

## Example File Breakdown

1. `01_quick_start.php`
   Demonstrates the absolute simplest API: zero-config one-liners for the most common operations. Post-request cost from API usage, pre-request text estimation, chat estimation with ChatML overhead, and a multi-provider comparison loop.

2. `02_anthropic_prompt_caching.php`
   Implements Anthropic Prompt Caching end-to-end: cache WRITE (first request with 1.25× surcharge), cache READ (subsequent requests at 90% discount), break-even analysis, and multi-turn session accumulation.

3. `03_custom_price_tables.php`
   Configures `ArrayPriceTable`, `JsonFilePriceTable`, and `ChainedPriceTable`. Shows glob pattern resolution, priority delegation, and runtime `registerPrice()` on a live engine instance.

4. `04_pricing_registry.php`
   Extends price tables using `PricingRegistry` with eager `register()` and lazy `registerFactory()` closures. Demonstrates glob pattern factories, engine binding via `withRegistry()`, unknown model fallback, and pre-flight price inspection.

5. `05_openai_cached_input.php`
   Explains and verifies the OpenAI subset caching model (`cacheReadIsSubsetOfInput = true`). Includes manual cost verification, savings comparison, Anthropic vs OpenAI side-by-side, and a multi-turn conversation accumulation pattern.

6. `06_vision_multimodal.php`
   Transforms pixel dimensions into token counts using provider-specific formulas (OpenAI tile formula, Anthropic `w×h/750`, Gemini tiles). Covers high vs low detail, `auto` mode, multi-image requests, provider comparison, `toArray()` serialization, and custom flat-rate estimators.

7. `07_tool_calling_loop.php`
   Configures a realistic 4-turn agentic web-research loop with Anthropic Prompt Caching. Demonstrates immutable `add()` accumulation, cache write + read turns, worst-case comparison, dynamic accumulation in a loop, and a budget guard pattern.

8. `08_dependency_injection.php`
   Shows production-grade DI wiring: manual constructor setup, a `CostCalculator` service type-hinting `PricingEngineInterface`, test isolation with `NullPriceTable`, a `RequestDispatcher` budget guard, static factory variants, and all three injectable axes together (text tokenizer + price table + image estimators).

9. `09_custom_tokenizer.php`
   Covers all eight tokenizer injection mechanisms: `register()` (eager instance), `registerFactory()` (lazy closure), `addProvider()` (dynamic `TokenizerProviderInterface`), `HuggingFaceJsonStrategy` (BPE from tokenizer.json), custom `TokenizerInterface` implementation, glob patterns, suppressed load-error warnings, and full DI wiring.

10. `10_custom_image_estimators.php`
    Covers all custom image estimator patterns: flat-rate (fixed tokens per image), dimension-based (megapixels), detail-aware tiling, overriding a built-in provider formula (e.g. replacing `AnthropicImageEstimator`), priority chains, model-family wildcards, and full three-axis DI setup.

11. `11_cost_reporting.php`
    Demonstrates every way to present and serialize `PricingResult` objects: `format()`, `formatDetailed()`, `toArray()`, JSON round-trips, per-session accumulation reports, structured JSON audit log entries, and `MultimodalPricingResult` extended serialization.

12. `12_gemini_context_window.php`
    Covers Gemini-specific pricing: standard sub-200K context rate, overriding to the >200K high-context tier via `ChainedPriceTable`, Gemini context caching (subset-style, same as OpenAI), Flash vs Pro tier comparison, and Gemini Vision multimodal.

13. `13_testing_patterns.php`
    Testing strategies for code that depends on `PricingEngineInterface`: `NullPriceTable` for zero-cost tests, controlled `ArrayPriceTable` with round-number prices for deterministic assertions, a fake `PricingEngineInterface` implementation for call-capture, caching math verification (additive vs subset), budget guard determinism, and `isZero()` / `isUnknownModel()` graceful-degradation assertions.

---

> **← Back:** [Flow Diagrams](flow-diagrams.md) · **Next:** [Troubleshooting →](troubleshooting.md)
