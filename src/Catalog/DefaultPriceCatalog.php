<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Catalog;

use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

/**
 * Built-in price catalog for all major LLM providers.
 *
 * IMPORTANT: AI pricing changes frequently. All entries are annotated with their
 * verification date and source. When a price is outdated, override it with a
 * JsonFilePriceTable or ChainedPriceTable rather than forking this file.
 *
 * Override example:
 *   $table = new ChainedPriceTable(
 *       new JsonFilePriceTable('/etc/myapp/current-prices.json'),
 *       new ArrayPriceTable(DefaultPriceCatalog::get()),  // fallback
 *   );
 *
 * Sources (all verified: 2026-05-20):
 *   OpenAI:     https://openai.com/api/pricing + https://developers.openai.com/api/docs/pricing
 *   Anthropic:  https://platform.claude.com/docs/en/about-claude/pricing
 *   Google:     https://ai.google.dev/gemini-api/docs/pricing
 *   DeepSeek:   https://api-docs.deepseek.com/quick_start/pricing/
 *   Groq:       https://groq.com/pricing
 *   Mistral:    https://mistral.ai/technology/#pricing (aggregator cross-verified)
 *   Perplexity: https://docs.perplexity.ai/guides/pricing
 */
final class DefaultPriceCatalog
{
    /**
     * Return all built-in ModelPrice instances.
     *
     * @return list<ModelPrice>
     */
    public static function get(): array
    {
        return [
            ...self::openAi(),
            ...self::anthropic(),
            ...self::google(),
            ...self::deepseek(),
            ...self::groq(),
            ...self::mistral(),
            ...self::perplexity(),
            ...self::imageGeneration(),
        ];
    }

    // ─── OpenAI ───────────────────────────────────────────────────────────────
    // Source: openai.com/api/pricing | Verified: 2026-05-20
    // Caching: automatic (no write surcharge), cacheReadIsSubsetOfInput=true
    // Batch: 50% discount for async batch API (not modeled here — use batch-specific ModelPrice)

    /** @return list<ModelPrice> */
    private static function openAi(): array
    {
        $src = 'Source: openai.com/api/pricing | Verified: 2026-05-20';

        return [
            // ── GPT-5.x (current generation, May 2026) ────────────────────────
            new ModelPrice(
                model: 'gpt-5.5',
                inputPerMillion: 5.00,
                outputPerMillion: 30.00,
                cacheReadPerMillion: 0.50,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-5.4',
                inputPerMillion: 2.50,
                outputPerMillion: 15.00,
                cacheReadPerMillion: 0.25,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-5.4-mini',
                inputPerMillion: 0.75,
                outputPerMillion: 4.50,
                cacheReadPerMillion: 0.075,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-5.4-nano',
                inputPerMillion: 0.20,
                outputPerMillion: 1.25,
                cacheReadPerMillion: 0.02,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-5',
                inputPerMillion: 1.25,
                outputPerMillion: 10.00,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-5-mini',
                inputPerMillion: 0.25,
                outputPerMillion: 2.00,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-5-nano',
                inputPerMillion: 0.05,
                outputPerMillion: 0.40,
                notes: $src,
            ),

            // ── GPT-4.1 series ────────────────────────────────────────────────
            new ModelPrice(
                model: 'gpt-4.1',
                inputPerMillion: 2.00,
                outputPerMillion: 8.00,
                cacheReadPerMillion: 0.50,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-4.1-mini',
                inputPerMillion: 0.40,
                outputPerMillion: 1.60,
                cacheReadPerMillion: 0.10,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-4.1-nano',
                inputPerMillion: 0.10,
                outputPerMillion: 0.40,
                notes: $src,
            ),

            // ── GPT-4o (still available, superseded by 4.1) ───────────────────
            new ModelPrice(
                model: 'gpt-4o',
                inputPerMillion: 2.50,
                outputPerMillion: 10.00,
                cacheReadPerMillion: 1.25,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-4o-mini',
                inputPerMillion: 0.15,
                outputPerMillion: 0.60,
                cacheReadPerMillion: 0.075,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-4-turbo',
                inputPerMillion: 10.00,
                outputPerMillion: 30.00,
                notes: $src . ' — legacy, superseded',
            ),

            // ── o-series reasoning models ────────────────────────────────────
            new ModelPrice(
                model: 'o4-mini',
                inputPerMillion: 1.10,
                outputPerMillion: 4.40,
                cacheReadPerMillion: 0.275,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'o3',
                inputPerMillion: 2.00,
                outputPerMillion: 8.00,
                cacheReadPerMillion: 0.50,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — price dropped ~80% from launch',
            ),
            new ModelPrice(
                model: 'o3-mini',
                inputPerMillion: 1.10,
                outputPerMillion: 4.40,
                notes: $src,
            ),
            new ModelPrice(
                model: 'o3-pro',
                inputPerMillion: 20.00,
                outputPerMillion: 80.00,
                notes: $src,
            ),
            new ModelPrice(
                model: 'o1',
                inputPerMillion: 15.00,
                outputPerMillion: 60.00,
                notes: $src . ' — largely superseded by o3/o4-mini',
            ),
            new ModelPrice(
                model: 'o1-mini',
                inputPerMillion: 1.10,
                outputPerMillion: 4.40,
                notes: $src,
            ),
        ];
    }

    // ─── Anthropic ────────────────────────────────────────────────────────────
    // Source: platform.claude.com/docs/en/about-claude/pricing | Verified: 2026-05-20
    // Prompt Caching:
    //   - cache_write (5-min TTL): 1.25× input price
    //   - cache_write (1-hr TTL):  2.00× input price (not modeled separately; use withModel())
    //   - cache_read:              0.10× input price  (90% discount)
    //   - cacheReadIsSubsetOfInput = false (Anthropic tokens are additive)
    // Minimum block sizes: Opus=4096, Sonnet=1024, Haiku=4096 tokens
    // Batch API: 50% discount (not modeled here)

    /** @return list<ModelPrice> */
    private static function anthropic(): array
    {
        $src = 'Source: platform.claude.com/docs/en/about-claude/pricing | Verified: 2026-05-20';

        return [
            // ── Claude Opus 4.x (flagship) ────────────────────────────────────
            // $5.00 input × 1.25 = $6.25 cache_write_5min | × 0.10 = $0.50 cache_read
            new ModelPrice(
                model: 'claude-opus-4-7',
                inputPerMillion: 5.00,
                outputPerMillion: 25.00,
                cacheWritePerMillion: 6.25,
                cacheReadPerMillion: 0.50,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — current flagship. Min cache block: 4096 tokens.',
            ),
            new ModelPrice(
                model: 'claude-opus-4-6',
                inputPerMillion: 5.00,
                outputPerMillion: 25.00,
                cacheWritePerMillion: 6.25,
                cacheReadPerMillion: 0.50,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — Min cache block: 4096 tokens.',
            ),
            new ModelPrice(
                model: 'claude-opus-4-5',
                inputPerMillion: 5.00,
                outputPerMillion: 25.00,
                cacheWritePerMillion: 6.25,
                cacheReadPerMillion: 0.50,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — Min cache block: 4096 tokens.',
            ),

            // ── Claude Opus 4.1 (previous major, higher priced) ───────────────
            new ModelPrice(
                model: 'claude-opus-4-1',
                inputPerMillion: 15.00,
                outputPerMillion: 75.00,
                cacheWritePerMillion: 18.75,
                cacheReadPerMillion: 1.50,
                cacheReadIsSubsetOfInput: false,
                notes: $src,
            ),

            // ── Claude Sonnet 4.x ─────────────────────────────────────────────
            // $3.00 input × 1.25 = $3.75 cache_write | × 0.10 = $0.30 cache_read
            new ModelPrice(
                model: 'claude-sonnet-4-6',
                inputPerMillion: 3.00,
                outputPerMillion: 15.00,
                cacheWritePerMillion: 3.75,
                cacheReadPerMillion: 0.30,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — Min cache block: 1024 tokens.',
            ),
            new ModelPrice(
                model: 'claude-sonnet-4-5',
                inputPerMillion: 3.00,
                outputPerMillion: 15.00,
                cacheWritePerMillion: 3.75,
                cacheReadPerMillion: 0.30,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — Min cache block: 1024 tokens.',
            ),
            // claude-sonnet-4-20250514 = legacy alias (retiring 2026-06-15)
            new ModelPrice(
                model: 'claude-sonnet-4-20250514',
                inputPerMillion: 3.00,
                outputPerMillion: 15.00,
                cacheWritePerMillion: 3.75,
                cacheReadPerMillion: 0.30,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — DEPRECATED: retiring 2026-06-15, migrate to claude-sonnet-4-6',
            ),

            // ── Claude Haiku 4.x ─────────────────────────────────────────────
            // $1.00 input × 1.25 = $1.25 cache_write | × 0.10 = $0.10 cache_read
            new ModelPrice(
                model: 'claude-haiku-4-5-20251001',
                inputPerMillion: 1.00,
                outputPerMillion: 5.00,
                cacheWritePerMillion: 1.25,
                cacheReadPerMillion: 0.10,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — Min cache block: 4096 tokens.',
            ),
            new ModelPrice(
                model: 'claude-haiku-4-5',
                inputPerMillion: 1.00,
                outputPerMillion: 5.00,
                cacheWritePerMillion: 1.25,
                cacheReadPerMillion: 0.10,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — Min cache block: 4096 tokens.',
            ),

            // ── Claude Haiku 3.5 (deprecated, Bedrock/Vertex only) ────────────
            new ModelPrice(
                model: 'claude-haiku-3-5',
                inputPerMillion: 0.80,
                outputPerMillion: 4.00,
                cacheWritePerMillion: 1.00,
                cacheReadPerMillion: 0.08,
                cacheReadIsSubsetOfInput: false,
                notes: $src . ' — DEPRECATED: Bedrock/Vertex only.',
            ),
            // Legacy alias used by older nexus-ai code
            new ModelPrice(
                model: 'claude-3-haiku',
                inputPerMillion: 0.25,
                outputPerMillion: 1.25,
                notes: $src . ' — claude-3-haiku (Claude 3, not 3.5). Legacy.',
            ),
        ];
    }

    // ─── Google Gemini ────────────────────────────────────────────────────────
    // Source: ai.google.dev/gemini-api/docs/pricing | Verified: 2026-05-20
    // Context caching: Gemini uses per-token READ price + per-hour storage (storage not modeled).
    // Pricing tiers (Standard / Batch / Flex / Priority) — Standard rate listed here.
    // NOTE: "high/medium/low" tier names seen in third-party gateways (e.g. OpenRouter, Vertex)
    //       are NOT official Gemini API model IDs. Override via JsonFilePriceTable for those.
    // NOTE: gemini-3.5-pro does NOT appear on the official pricing page (gemini-3.1-pro-preview
    //       is the current Pro-tier model as of 2026-05-20).

    /** @return list<ModelPrice> */
    private static function google(): array
    {
        $src = 'Source: ai.google.dev/gemini-api/docs/pricing | Verified: 2026-05-20';

        return [
            // ── Gemini 3.5 Flash (flagship, current gen) ──────────────────────
            new ModelPrice(
                model: 'gemini-3.5-flash',
                inputPerMillion: 1.50,
                outputPerMillion: 9.00,
                cacheReadPerMillion: 0.15,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Current flagship. Cache storage: $1.00/MTok/hr.',
            ),

            // ── Gemini 3.1 ────────────────────────────────────────────────────
            // Pro preview: tiered by context length (≤200k / >200k)
            // customtools variant: same price, different function-calling capabilities
            new ModelPrice(
                model: 'gemini-3.1-pro-preview',
                inputPerMillion: 2.00,
                outputPerMillion: 12.00,
                cacheReadPerMillion: 0.20,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — ≤200k ctx: $2.00/$12.00. >200k ctx: $4.00/$18.00.',
            ),
            new ModelPrice(
                model: 'gemini-3.1-pro-preview-customtools',
                inputPerMillion: 2.00,
                outputPerMillion: 12.00,
                cacheReadPerMillion: 0.20,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Same pricing as gemini-3.1-pro-preview. ≤200k ctx: $2.00/$12.00. >200k ctx: $4.00/$18.00. Extended function-calling capabilities.',
            ),
            new ModelPrice(
                model: 'gemini-3.1-flash-lite',
                inputPerMillion: 0.25,
                outputPerMillion: 1.50,
                cacheReadPerMillion: 0.025,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gemini-3.1-flash-lite-preview',
                inputPerMillion: 0.25,
                outputPerMillion: 1.50,
                cacheReadPerMillion: 0.025,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Preview alias for gemini-3.1-flash-lite.',
            ),
            // Live (real-time streaming) variant — text token rates listed
            new ModelPrice(
                model: 'gemini-3.1-flash-live-preview',
                inputPerMillion: 0.75,
                outputPerMillion: 4.50,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Live/streaming API. Audio input: $3.00/M. Audio output: $12.00/M.',
            ),

            // ── Gemini 3.0 (preview) ──────────────────────────────────────────
            new ModelPrice(
                model: 'gemini-3-flash-preview',
                inputPerMillion: 0.50,
                outputPerMillion: 3.00,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),

            // ── Gemini 2.5 ────────────────────────────────────────────────────
            // Pro: tiered ≤200k = $1.25, >200k = $2.50 input
            new ModelPrice(
                model: 'gemini-2.5-pro',
                inputPerMillion: 1.25,
                outputPerMillion: 10.00,
                cacheReadPerMillion: 0.125,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — ≤200k ctx: $1.25/$10.00. >200k ctx: $2.50/$15.00. Cache storage: $4.50/MTok/hr.',
            ),
            new ModelPrice(
                model: 'gemini-2.5-flash',
                inputPerMillion: 0.30,
                outputPerMillion: 2.50,
                cacheReadPerMillion: 0.03,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Cache storage: $1.00/MTok/hr.',
            ),
            new ModelPrice(
                model: 'gemini-2.5-flash-lite',
                inputPerMillion: 0.10,
                outputPerMillion: 0.40,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gemini-2.5-flash-lite-preview-09-2025',
                inputPerMillion: 0.10,
                outputPerMillion: 0.40,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Preview variant; same price as gemini-2.5-flash-lite.',
            ),
            // Native audio — text token rates listed; audio has separate per-M pricing
            new ModelPrice(
                model: 'gemini-2.5-flash-native-audio-preview-12-2025',
                inputPerMillion: 0.50,
                outputPerMillion: 2.00,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Text rates. Audio input: $3.00/M. Audio output: $12.00/M.',
            ),
            // Computer Use — same pricing as 2.5-pro (≤200k tier)
            new ModelPrice(
                model: 'gemini-2.5-computer-use-preview-10-2025',
                inputPerMillion: 1.25,
                outputPerMillion: 10.00,
                cacheReadPerMillion: 0.125,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — ≤200k ctx: $1.25/$10.00. >200k ctx: $2.50/$15.00.',
            ),

            // ── Gemini 2.0 (deprecated) ───────────────────────────────────────
            new ModelPrice(
                model: 'gemini-2.0-flash',
                inputPerMillion: 0.10,
                outputPerMillion: 0.40,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — DEPRECATED.',
            ),
            new ModelPrice(
                model: 'gemini-2.0-flash-lite',
                inputPerMillion: 0.075,
                outputPerMillion: 0.30,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — DEPRECATED.',
            ),

            // ── Gemini 1.5 (deprecated, removed from official pricing page) ───
            new ModelPrice(
                model: 'gemini-1.5-pro',
                inputPerMillion: 3.50,
                outputPerMillion: 10.50,
                notes: $src . ' — DEPRECATED: removed from official pricing page 2026.',
            ),
            new ModelPrice(
                model: 'gemini-1.5-flash',
                inputPerMillion: 0.075,
                outputPerMillion: 0.30,
                notes: $src . ' — DEPRECATED: removed from official pricing page 2026.',
            ),
        ];
    }

    // ─── DeepSeek ─────────────────────────────────────────────────────────────
    // Source: api-docs.deepseek.com/quick_start/pricing/ | Verified: 2026-05-20
    // DeepSeek uses cache HIT pricing (automatic, no write surcharge)
    // deepseek-chat and deepseek-reasoner are aliases, deprecated 2026-07-24
    // deepseek-v4-pro has 75% promotional discount active through 2026-05-31

    /** @return list<ModelPrice> */
    private static function deepseek(): array
    {
        $src = 'Source: api-docs.deepseek.com/quick_start/pricing/ | Verified: 2026-05-20';

        return [
            // deepseek-v4-flash (standard model; aliases: deepseek-chat, deepseek-reasoner)
            new ModelPrice(
                model: 'deepseek-v4-flash',
                inputPerMillion: 0.14,
                outputPerMillion: 0.28,
                cacheReadPerMillion: 0.0028,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Cache miss: $0.14/M, cache hit: $0.0028/M (98% discount).',
            ),
            // Backward-compatible aliases (deprecated 2026-07-24)
            new ModelPrice(
                model: 'deepseek-chat',
                inputPerMillion: 0.14,
                outputPerMillion: 0.28,
                cacheReadPerMillion: 0.0028,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Alias for deepseek-v4-flash. DEPRECATED alias: 2026-07-24.',
            ),
            new ModelPrice(
                model: 'deepseek-reasoner',
                inputPerMillion: 0.14,
                outputPerMillion: 0.28,
                cacheReadPerMillion: 0.0028,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — Alias for deepseek-v4-flash. DEPRECATED alias: 2026-07-24.',
            ),
            // deepseek-v4-pro (promo -75% until 2026-05-31; full price: $1.74/$3.48)
            new ModelPrice(
                model: 'deepseek-v4-pro',
                inputPerMillion: 0.435,
                outputPerMillion: 0.87,
                cacheReadPerMillion: 0.003625,
                cacheReadIsSubsetOfInput: true,
                notes: $src . ' — PROMO -75% until 2026-05-31. Full price: $1.74/$3.48. Update after promo ends.',
            ),
        ];
    }

    // ─── Groq ─────────────────────────────────────────────────────────────────
    // Source: groq.com/pricing | Verified: 2026-05-20
    // Groq hosts open-source models with fast inference; prices are per MTok

    /** @return list<ModelPrice> */
    private static function groq(): array
    {
        $src = 'Source: groq.com/pricing | Verified: 2026-05-20';

        return [
            new ModelPrice(
                model: 'llama-3.3-70b-versatile',
                inputPerMillion: 0.59,
                outputPerMillion: 0.79,
                notes: $src . ' — 128k context',
            ),
            new ModelPrice(
                model: 'llama-3.1-8b-instant',
                inputPerMillion: 0.05,
                outputPerMillion: 0.08,
                notes: $src . ' — 128k context',
            ),
            new ModelPrice(
                model: 'llama-4-scout',
                inputPerMillion: 0.11,
                outputPerMillion: 0.34,
                notes: $src . ' — Llama 4 Scout 17Bx16E, 128k context',
            ),
            new ModelPrice(
                model: 'qwen3-32b',
                inputPerMillion: 0.29,
                outputPerMillion: 0.59,
                notes: $src . ' — 131k context',
            ),
            new ModelPrice(
                model: 'kimi-k2-instruct',
                inputPerMillion: 1.00,
                outputPerMillion: 3.00,
                cacheReadPerMillion: 0.50,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            // GPT OSS models on Groq
            new ModelPrice(
                model: 'gpt-oss-20b',
                inputPerMillion: 0.075,
                outputPerMillion: 0.30,
                cacheReadPerMillion: 0.0375,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            new ModelPrice(
                model: 'gpt-oss-120b',
                inputPerMillion: 0.15,
                outputPerMillion: 0.60,
                cacheReadPerMillion: 0.075,
                cacheReadIsSubsetOfInput: true,
                notes: $src,
            ),
            // Mixtral kept for legacy compatibility (deprecated on Groq)
            new ModelPrice(
                model: 'mixtral-8x7b-32768',
                inputPerMillion: 0.27,
                outputPerMillion: 0.27,
                notes: $src . ' — DEPRECATED on Groq as of 2026.',
            ),
        ];
    }

    // ─── Mistral AI ───────────────────────────────────────────────────────────
    // Source: mistral.ai/technology/#pricing (aggregator cross-verified) | Verified: 2026-05-20
    // Note: Mistral's public pricing page shows subscriptions only; API prices from aggregators.
    // Verify via Mistral console before production use. No batch/cache discount documented.

    /** @return list<ModelPrice> */
    private static function mistral(): array
    {
        $src = 'Source: mistral.ai API (aggregator cross-verified) | Verified: 2026-05-20 — verify via console';

        return [
            // Mistral Large 3 = current recommended (2512 revision, $0.50/$1.50)
            // Mistral Large 2 = previous gen (2411/2407, $2.00/$6.00)
            new ModelPrice(
                model: 'mistral-large-latest',
                inputPerMillion: 0.50,
                outputPerMillion: 1.50,
                notes: $src . ' — Mistral Large 3 (2512 revision).',
            ),
            new ModelPrice(
                model: 'mistral-large-2411',
                inputPerMillion: 2.00,
                outputPerMillion: 6.00,
                notes: $src . ' — Mistral Large 2 (2411 revision, previous gen).',
            ),
            new ModelPrice(
                model: 'mistral-medium-latest',
                inputPerMillion: 0.40,
                outputPerMillion: 2.00,
                notes: $src . ' — Mistral Medium 3.1.',
            ),
            new ModelPrice(
                model: 'mistral-small-latest',
                inputPerMillion: 0.075,
                outputPerMillion: 0.20,
                notes: $src . ' — Mistral Small 3.2.',
            ),
            new ModelPrice(
                model: 'codestral-latest',
                inputPerMillion: 0.30,
                outputPerMillion: 0.90,
                notes: $src . ' — Codestral 2508.',
            ),
            new ModelPrice(
                model: 'mistral-nemo',
                inputPerMillion: 0.02,
                outputPerMillion: 0.03,
                notes: $src,
            ),
            new ModelPrice(
                model: 'ministral-8b-latest',
                inputPerMillion: 0.10,
                outputPerMillion: 0.10,
                notes: $src,
            ),
            new ModelPrice(
                model: 'pixtral-large-latest',
                inputPerMillion: 2.00,
                outputPerMillion: 6.00,
                notes: $src . ' — Pixtral Large 2411, multimodal.',
            ),
            new ModelPrice(
                model: 'pixtral-12b',
                inputPerMillion: 0.10,
                outputPerMillion: 0.10,
                notes: $src . ' — Pixtral 12B, multimodal.',
            ),
        ];
    }

    // ─── Perplexity / Sonar ───────────────────────────────────────────────────
    // Source: docs.perplexity.ai/guides/pricing | Verified: 2026-05-20
    // NOTE: Perplexity charges PER-REQUEST FEES in addition to token costs.
    // These per-request fees ($5-$14 per 1000 requests) are NOT modeled here.
    // The token prices below cover only the token portion of the cost.

    /** @return list<ModelPrice> */
    private static function perplexity(): array
    {
        $src = 'Source: docs.perplexity.ai/guides/pricing | Verified: 2026-05-20 — PER-REQUEST FEES NOT INCLUDED';

        return [
            new ModelPrice(
                model: 'sonar-pro',
                inputPerMillion: 3.00,
                outputPerMillion: 15.00,
                notes: $src,
            ),
            new ModelPrice(
                model: 'sonar',
                inputPerMillion: 1.00,
                outputPerMillion: 1.00,
                notes: $src,
            ),
            new ModelPrice(
                model: 'sonar-reasoning-pro',
                inputPerMillion: 2.00,
                outputPerMillion: 8.00,
                notes: $src,
            ),
            new ModelPrice(
                model: 'sonar-deep-research',
                inputPerMillion: 2.00,
                outputPerMillion: 8.00,
                notes: $src . ' — Also charges citation tokens ($2/M) and search ($5/1K queries).',
            ),
        ];
    }

    /** @return list<ModelPrice> */
    private static function imageGeneration(): array
    {
        $src = 'Source: openai.com/api/pricing + docs.x.ai + ai.google.dev | Verified: 2026-05-22';

        return [
            // —— OpenAI image generation ——————————————————————————————————
            new ModelPrice(
                'gpt-image-1',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                notes: $src,
            ),
            new ModelPrice(
                'gpt-image-1-mini',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                notes: $src,
            ),
            new ModelPrice(
                'gpt-image-2',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                notes: $src,
            ),
            new ModelPrice(
                'gpt-image-2-2026-04-21',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                notes: $src,
            ),
            new ModelPrice(
                'chatgpt-image-latest',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                notes: $src,
            ),

            // —— xAI Grok (per-image → tokens sintéticos) ——————————————————
            new ModelPrice(
                'grok-imagine-image-quality',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                perImageCost: 0.05,
                notes: $src . ' — $0.05/1k, $0.10/2k. Modelado como tokens sintéticos.',
            ),
            new ModelPrice(
                'grok-imagine-image-quality-latest',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                perImageCost: 0.05,
                notes: $src,
            ),
            new ModelPrice(
                'grok-imagine-image-pro',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                perImageCost: 0.05,
                notes: $src . ' — DEPRECATED May 2026.',
            ),

            // —— Google Gemini image generation ———————————————————————————
            new ModelPrice(
                'gemini-2.5-flash-image',
                inputPerMillion: 0.30,
                outputPerMillion: 0.60,
                imageOutputPerMillion: 0.60,
                notes: $src . ' — 4K image ≈ 2,520 tokens ≈ $0.151.',
            ),

            // —— Glob patterns para variantes futuras ——————————————————————
            new ModelPrice(
                'gpt-image-*',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                notes: $src,
            ),
            new ModelPrice(
                'grok-imagine-*',
                inputPerMillion: 5.00,
                outputPerMillion: 0.0,
                imageOutputPerMillion: 40.00,
                perImageCost: 0.05,
                notes: $src,
            ),
        ];
    }
}
