# Changelog

All notable changes to `token27/nexus-ai-pricing` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [1.0.0] — 2026-05-20

### Added

**Core pricing engine**
- `PricingEngine` — main facade with static `for($model)` entry point and DI-friendly constructor
- `PricingBuilder` — fluent interface returned by `PricingEngine::for()` and `PricingEngine::make()`
- `PricingEngineInterface` — contract for DI and testing

**Price tables**
- `ArrayPriceTable` — in-memory table with exact ID + glob pattern matching (longest pattern wins)
- `JsonFilePriceTable` — load prices from an external JSON file; lazy-loaded and memory-cached
- `ChainedPriceTable` — delegate to multiple tables in priority order; `prepend()`/`append()` for immutable composition
- `NullPriceTable` — always returns zero-cost, useful for tests
- `PriceTableInterface` — contract for all price table implementations

**Price registry**
- `PricingRegistry` — advanced table with glob patterns, lazy factories, and `createDefault()` from catalog

**Value objects**
- `ModelPrice` — immutable price descriptor; supports Anthropic cache (additive) and OpenAI cache (subset) models
- `PricingResult` — immutable result; `add()` combines results from multi-step loops; `isUnknownModel()` flag
- `MultimodalPricingResult` — extends `PricingResult` with image token costs
- `ImageAttachment` — descriptor for vision prompts (widthPx, heightPx, detail)

**Built-in catalog (`DefaultPriceCatalog`)**
- OpenAI: gpt-5.5, gpt-5.4, gpt-5.4-mini, gpt-5.4-nano, gpt-5, gpt-5-mini, gpt-4.1, gpt-4.1-mini, gpt-4o, gpt-4o-mini, gpt-4-turbo, o4-mini, o3, o3-mini, o1, o1-mini
- Anthropic: claude-opus-4-7/4-6/4-5/4-1, claude-sonnet-4-6/4-5 (+ legacy 4-20250514), claude-haiku-4-5/3-5
- Google Gemini: gemini-3.5-flash, gemini-3.1-pro-preview, gemini-3.1-flash-lite, gemini-2.5-pro, gemini-2.5-flash, gemini-2.5-flash-lite (+ deprecated 1.5 entries for backward compat)
- DeepSeek: deepseek-v4-flash, deepseek-v4-pro + aliases
- Groq: llama-3.3-70b, llama-3.1-8b, llama-4-scout, qwen3-32b, kimi-k2-instruct, gpt-oss-20b/120b
- Mistral: mistral-large-latest, mistral-medium-latest, mistral-small-latest, codestral-latest, mistral-nemo, ministral-8b, pixtral-large, pixtral-12b
- Perplexity: sonar, sonar-pro, sonar-reasoning-pro, sonar-deep-research
- All prices verified: **2026-05-20**

**Caching models correctly implemented**
- Anthropic-style (additive): `cacheWritePerMillion` (1.25×) + `cacheReadPerMillion` (0.10×)
- OpenAI-style (subset): `cacheReadPerMillion` at discounted rate; `cacheReadIsSubsetOfInput = true`
- `cacheSavingsUsd()` computed for both models

**Vision / multimodal**
- `PricingEngine::estimateWithImages()` fully injectable — three independent axes:
  - Text tokenizer via `TokenizerInterface` (was previously hard-coded)
  - Price table via `PriceTableInterface`
  - Image estimators via `list<ImageTokenEstimatorInterface>` (new `imageEstimators` constructor param)
- Built-in estimators used by default when `imageEstimators` is empty:
  - `OpenAIImageEstimator`: tile-based (512×512, low=85 flat, high=85+170×tiles)
  - `AnthropicImageEstimator`: `(width × height) / 750`
  - `GeminiImageEstimator`: tile-based (768×768, 258 tokens/tile)
- Custom estimators: implement `ImageTokenEstimatorInterface` and inject via constructor
- Resolution order: first `supports($model) === true` wins; fallback to `OpenAIImageEstimator`

**Examples**
- `examples/09_custom_tokenizer.php` — all eight custom tokenizer injection mechanisms
- `examples/10_custom_image_estimators.php` — all custom image estimator patterns

**Exceptions**
- `UnknownModelPriceException` — opt-in strict mode; engine itself never throws for unknown models
- `PriceTableException` — for `JsonFilePriceTable` I/O errors

**Tests**
- `ModelPriceTest` — VO behavior and sentinel
- `PricingResultTest` — calculation correctness for OpenAI, Anthropic cache, OpenAI subset cache, add(), toArray(), catalog sanity check
- `ArrayPriceTableTest` — exact match, glob match, longest-wins, setPrice, hasPrice
- `ChainedPriceTableTest` — priority, fallback, dedup, prepend()
- `JsonFilePriceTableTest` — load from fixture, glob in JSON, lazy loading, missing file error
- `PricingEngineTest` — calculate, estimate, unknown model, ChainedPriceTable priority, registerPrice, estimateWithImages

[1.0.0]: https://github.com/token27/nexus-ai-pricing/releases/tag/v1.0.0
