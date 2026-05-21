# Default Price Catalog & Officially Supported Models

The `nexus-ai-pricing` engine includes a deeply researched, verified standard catalog encapsulating live market constraints for all major AI providers. When initializing the engine with `DefaultPriceCatalog::get()`, all these properties become statically available to your application.

> [!NOTE]
> **Verification Benchmark:** May 2026.
> AI pricing shifts constantly. While these prices serve as the default baseline, enterprise-scale applications should typically utilize internal `JsonFilePriceTable` registries to adjust shifting metrics without updating the core package.

All explicitly tracked costs mathematically represent **USD per 1,000,000 Tokens ($/M)**.

---

## 1. OpenAI

OpenAI mathematically utilizes a `subset` constraint caching model. `cache_read_is_subset_of_input` applies intrinsically to these variants, ensuring non-additive savings parsing against `prompt_tokens`.

| Base Identifier | Input $/M | Cached Input $/M | Output $/M |
|-----------------|----------:|-----------------:|-----------:|
| `gpt-5.5` | $5.00 | $0.50 | $30.00 |
| `gpt-5.4` | $2.50 | $0.25 | $15.00 |
| `gpt-5.4-mini` | $0.75 | $0.075 | $4.50 |
| `gpt-5.4-nano` | $0.20 | $0.02 | $1.25 |
| `gpt-5` | $1.25 | — | $10.00 |
| `gpt-5-mini` | $0.25 | — | $2.00 |
| `gpt-5-nano` | $0.05 | — | $0.40 |
| `gpt-4.1` | $2.00 | $0.50 | $8.00 |
| `gpt-4.1-mini` | $0.40 | $0.10 | $1.60 |
| `gpt-4o` | $2.50 | $1.25 | $10.00 |
| `gpt-4o-mini` | $0.15 | $0.075 | $0.60 |
| `o4-mini` | $1.10 | $0.275 | $4.40 |
| `o3` | $2.00 | $0.50 | $8.00 |
| `o3-mini` | $1.10 | — | $4.40 |
| `o3-pro` | $20.00 | — | $80.00 |
| `o1` | $15.00 | — | $60.00 |
| `o1-mini` | $1.10 | — | $4.40 |

> [!WARNING]
> Legacy variants like `gpt-4-turbo` stand formally deprecated inside the registry mapping, carrying massive API legacy markups ($10.00 / $30.00). Ensure endpoint rotation heavily prioritizes generating `4.1` or `gpt-5` properties instead.

---

## 2. Anthropic Claude

Anthropic utilizes purely *additive* prompt caching dynamics. Writing properties explicitly enforce a surcharge boundary that heavily mitigates repeated reading iterations over the subsequent `5-minute` temporal cache windows.

| Base Identifier | Input $/M | Cache Write (1.25x) | Cache Read (0.10x) | Output $/M |
|-----------------|----------:|--------------------:|-------------------:|-----------:|
| `claude-opus-4-7` | $5.00 | $6.25 | $0.50 | $25.00 |
| `claude-opus-4-6` | $5.00 | $6.25 | $0.50 | $25.00 |
| `claude-opus-4-1` | $15.00 | $18.75 | $1.50 | $75.00 |
| `claude-sonnet-4-6` | $3.00 | $3.75 | $0.30 | $15.00 |
| `claude-sonnet-4-5` | $3.00 | $3.75 | $0.30 | $15.00 |
| `claude-haiku-4-5-20251001`| $1.00 | $1.25 | $0.10 | $5.00 |
| `claude-haiku-4-5` | $1.00 | $1.25 | $0.10 | $5.00 |

*Note: Models possess explicit minimum array bounds for Cache mapping (Opus = 4096 min scale, Sonnet = 1024, Haiku = 4096).*
*Legacy note: `claude-sonnet-4-20250514` retires `2026-06-15`. You MUST swap strictly to `claude-sonnet-4-6`.*

---

## 3. Google Gemini

Gemini inherently processes complex variable scaled inputs. The catalog heavily targets context `<200K` limits natively formatting typical responses safely.

| Base Identifier | Input $/M | Cached Read $/M | Output $/M |
|-----------------|----------:|----------------:|-----------:|
| `gemini-3.5-flash` | $1.50 | $0.15 | $9.00 |
| `gemini-3.1-pro-preview` | $2.00 | $0.20 | $12.00 |
| `gemini-3.1-flash-lite` | $0.25 | — | $1.50 |
| `gemini-2.5-pro` | $1.25 | $0.125 | $10.00 |
| `gemini-2.5-flash` | $0.30 | $0.03 | $2.50 |

> [!CAUTION]
> Legacy strings targeting the 1.5 architectures (`gemini-1.5-pro`, `gemini-1.5-flash`) evaluate technically inside the code for explicit backwards continuity but completely lack API-published rates. Ensure pipelines dynamically upgrade mappings toward generation `2.5` or `3.5`.

---

## 4. DeepSeek

DeepSeek evaluates `Flash` operations implementing 98% native caching discounts against identical sequence branches dynamically.

| Base Identifier | Cache Miss Input $/M | Cache Hit $/M | Output $/M |
|-----------------|---------------------:|--------------:|-----------:|
| `deepseek-v4-flash` | $0.14 | $0.0028 | $0.28 |
| `deepseek-v4-pro` | $0.435 | $0.003625 | $0.87 |

> [!CAUTION]
> Aliases resolving to `deepseek-chat` and `deepseek-reasoner` face endpoint decommissioning explicitly on `2026-07-24`. Ensure the pipeline migrates correctly resolving strictly toward `deepseek-v4-flash`.
> Note that `deepseek-v4-pro` actively leverages a temporary -75% promotional bounds (`$0.435`) spanning exactly until `2026-05-31`.

---

## 5. Groq

Serving heavily streamlined Open-Source checkpoints targeting extremely high Tokens-Per-Second scaling constraints.

| Base Identifier | Input $/M | Cache Subset $/M | Output $/M |
|-----------------|----------:|-----------------:|-----------:|
| `llama-3.3-70b-versatile`| $0.59 | — | $0.79 |
| `llama-3.1-8b-instant` | $0.05 | — | $0.08 |
| `llama-4-scout` | $0.11 | — | $0.34 |
| `qwen3-32b` | $0.29 | — | $0.59 |
| `kimi-k2-instruct` | $1.00 | $0.50 | $3.00 |
| `gpt-oss-20b` | $0.075 | $0.0375 | $0.30 |
| `gpt-oss-120b` | $0.15 | $0.075 | $0.60 |

---

## 6. Mistral AI

*Verified against third-party network aggregation logic mapping constraints safely since API platforms utilize abstract subscription modifiers actively.*

| Base Identifier | Input $/M | Output $/M |
|-----------------|----------:|-----------:|
| `mistral-large-latest` | $0.50 | $1.50 |
| `mistral-large-2411` | $2.00 | $6.00 |
| `mistral-medium-latest` | $0.40 | $2.00 |
| `mistral-small-latest` | $0.075 | $0.20 |
| `codestral-latest` | $0.30 | $0.90 |
| `pixtral-large-latest` | $2.00 | $6.00 |
| `pixtral-12b` | $0.10 | $0.10 |

---

## 7. Perplexity (Sonar)

Perplexity implicitly layers **Per-Request** architectural charges independently against token logic processing endpoints bounds (Scaling $5 - $14 overhead independently per 1000 requests globally). This engine specifically scopes **tokens only**. It does not automatically append the per-request surcharge.

| Base Identifier | Input $/M | Output $/M |
|-----------------|----------:|-----------:|
| `sonar-pro` | $3.00 | $15.00 |
| `sonar` | $1.00 | $1.00 |
| `sonar-reasoning-pro` | $2.00 | $8.00 |
| `sonar-deep-research` | $2.00 | $8.00 |

---

> **← Back:** [Pricing Registry](pricing-registry.md) · **Next:** [Advanced Integration →](advanced-integration.md)
