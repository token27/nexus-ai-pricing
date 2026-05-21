<?php

/**
 * Example 10 — Custom Image Token Estimators
 *
 * PricingEngine accepts a custom list of ImageTokenEstimatorInterface instances
 * for image token counting. This example covers every use case:
 *
 *   1. Implementing ImageTokenEstimatorInterface from scratch (proprietary model)
 *   2. Flat-rate estimator — every image costs the same number of tokens
 *   3. Dimension-based estimator — tokens proportional to megapixels
 *   4. Detail-aware estimator — different formula per detail level
 *   5. Replacing a built-in provider formula (override Anthropic formula)
 *   6. Priority chain — custom first, built-ins as fallback
 *   7. Model-family wildcard — one estimator for a whole model family
 *   8. Full DI setup — all three axes injected together
 *
 * Related: examples/06_vision_multimodal.php — introduction to multimodal pricing
 *          examples/08_dependency_injection.php — DI wiring with all three axes
 *          examples/09_custom_tokenizer.php — custom text tokenizers
 *
 * ImageTokenEstimatorInterface (from nexus-ai-tokenizer):
 *   estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
 *   supports(string $model): bool
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;
use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\Tokenizer\Contract\ImageTokenEstimatorInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\ValueObject\TokenCount;
use Token27\Tokenizer\Vision\AnthropicImageEstimator;
use Token27\Tokenizer\Vision\GeminiImageEstimator;
use Token27\Tokenizer\Vision\OpenAIImageEstimator;

// ─── 1. Flat-rate estimator ───────────────────────────────────────────────────
//
// The simplest custom estimator: every image costs the same fixed token count
// regardless of dimensions or detail level.
// Use when the provider documents a flat per-image token charge.

$flatRateEstimator = new class implements ImageTokenEstimatorInterface {
    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        return new TokenCount(
            count: 500,
            model: $model,
            strategy: 'flat_rate_500',
            approximate: false,
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'my-vision-');
    }
};

$flatEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable([
        new ModelPrice('my-vision-model', inputPerMillion: 1.00, outputPerMillion: 4.00, imageInputPerMillion: 2.00),
    ]),
    imageEstimators: [$flatRateEstimator],
);

$flatResult = $flatEngine->make('my-vision-model')->estimateWithImages(
    text: 'Describe the defect in this component.',
    images: [
        ImageAttachment::highDetail(1920, 1080),
        ImageAttachment::highDetail(800, 600),
        ImageAttachment::lowDetail(400, 300),
    ],
);

echo "=== 1. Flat-rate estimator (500 tokens per image) ===" . PHP_EOL;
printf('3 images × 500 tokens = %d image tokens%s', $flatResult->imageTokens(), PHP_EOL);
printf('Image cost: $%.6f  |  Total: %s%s', $flatResult->imageCostUsd(), $flatResult->format(), PHP_EOL);
echo PHP_EOL;

// ─── 2. Dimension-based estimator ────────────────────────────────────────────
//
// Tokens proportional to image area (megapixels), rounded to a minimum.
// Useful for providers that bill image tokens linearly by pixel count.

class MegapixelEstimator implements ImageTokenEstimatorInterface
{
    public function __construct(
        private readonly string $modelPrefix,
        private readonly int    $tokensPerMegapixel = 200,
        private readonly int    $minimumTokens      = 50,
    ) {}

    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        $megapixels = ($widthPx * $heightPx) / 1_000_000;
        $tokens     = max($this->minimumTokens, (int) round($megapixels * $this->tokensPerMegapixel));

        return new TokenCount(
            count: $tokens,
            model: $model,
            strategy: 'megapixel_linear',
            approximate: true,
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, $this->modelPrefix);
    }
}

$mpEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable([
        new ModelPrice('acme-vision-*', inputPerMillion: 0.50, outputPerMillion: 2.00, imageInputPerMillion: 1.00),
    ]),
    imageEstimators: [new MegapixelEstimator('acme-vision-')],
);

echo "=== 2. Dimension-based estimator (200 tokens/MP, min 50) ===" . PHP_EOL;

$images2d = [
    ['label' => '1920×1080 (2.07 MP)', 'img' => ImageAttachment::highDetail(1920, 1080)],
    ['label' => ' 800× 600 (0.48 MP)', 'img' => ImageAttachment::highDetail(800, 600)],
    ['label' => ' 400× 300 (0.12 MP)', 'img' => ImageAttachment::lowDetail(400, 300)],
];

foreach ($images2d as $item) {
    $r = $mpEngine->make('acme-vision-v1')->estimateWithImages(
        text: 'x',
        images: [$item['img']],
    );
    printf('%s → %4d tokens  $%.6f%s', $item['label'], $r->imageTokens(), $r->imageCostUsd(), PHP_EOL);
}
echo PHP_EOL;

// ─── 3. Detail-aware estimator ────────────────────────────────────────────────
//
// Different token formula per detail level: high uses tiling, low is fixed.
// This mirrors what providers like OpenAI do under the hood.

class DetailAwareEstimator implements ImageTokenEstimatorInterface
{
    private const LOW_TOKENS  = 85;
    private const TILE_SIZE   = 512;
    private const BASE_TOKENS = 85;

    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        if ($detail === 'low') {
            return new TokenCount(count: self::LOW_TOKENS, model: $model, strategy: 'detail_aware_low', approximate: false);
        }

        // High detail: scale to 2048px longest side, then tile at 512px
        $scale = min(1.0, 2048 / max($widthPx, $heightPx));
        $w     = (int) round($widthPx  * $scale);
        $h     = (int) round($heightPx * $scale);
        $tiles = (int) ceil($w / self::TILE_SIZE) * (int) ceil($h / self::TILE_SIZE);
        $count = $tiles * 170 + self::BASE_TOKENS;

        return new TokenCount(count: $count, model: $model, strategy: 'detail_aware_high', approximate: false);
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'enterprise-vision-');
    }
}

$detailEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable([
        new ModelPrice('enterprise-vision-v2', inputPerMillion: 3.00, outputPerMillion: 12.00, imageInputPerMillion: 5.00),
    ]),
    imageEstimators: [new DetailAwareEstimator()],
);

$highDetailR = $detailEngine->make('enterprise-vision-v2')->estimateWithImages(
    text: 'Identify all components in this circuit diagram.',
    images: [ImageAttachment::highDetail(1920, 1080)],
);

$lowDetailR = $detailEngine->make('enterprise-vision-v2')->estimateWithImages(
    text: 'Is there anything in this image?',
    images: [ImageAttachment::lowDetail(1920, 1080)],
);

echo "=== 3. Detail-aware estimator (1920×1080) ===" . PHP_EOL;
printf('High detail: %d tokens  $%.6f%s', $highDetailR->imageTokens(), $highDetailR->imageCostUsd(), PHP_EOL);
printf('Low  detail: %d tokens  $%.6f%s', $lowDetailR->imageTokens(), $lowDetailR->imageCostUsd(), PHP_EOL);
echo PHP_EOL;

// ─── 4. Override a built-in provider formula ──────────────────────────────────
//
// You can replace the built-in Anthropic formula (w×h / 750) with a custom one.
// Place your estimator BEFORE the built-in AnthropicImageEstimator in the chain.
// The first estimator whose supports($model) returns true wins — the built-in is skipped.

class CustomAnthropicEstimator implements ImageTokenEstimatorInterface
{
    // Using (w×h / 600) instead of the default (w×h / 750)
    // Example: your testing shows a slightly different token-per-pixel ratio.
    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        $tokens = (int) ceil(($widthPx * $heightPx) / 600);

        return new TokenCount(
            count: $tokens,
            model: $model,
            strategy: 'custom_anthropic_wh600',
            approximate: true,
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'claude-');
    }
}

$overrideEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable(DefaultPriceCatalog::get()),
    imageEstimators: [
        new CustomAnthropicEstimator(), // replaces built-in for claude-* (comes first)
        new OpenAIImageEstimator(),     // built-in for gpt-*, o1, o3, …
        new GeminiImageEstimator(),     // built-in for gemini-*
        // AnthropicImageEstimator intentionally omitted — CustomAnthropicEstimator handles it
    ],
);

echo "=== 4. Override built-in Anthropic formula ===" . PHP_EOL;

foreach (['claude-sonnet-4-6', 'gpt-4o'] as $model) {
    $r = $overrideEngine->make($model)->estimateWithImages(
        text: 'Analyse this architectural diagram.',
        images: [ImageAttachment::highDetail(1920, 1080)],
    );
    printf('%-22s  image tokens: %d  total: %s%s', $model, $r->imageTokens(), $r->format(), PHP_EOL);
}
echo PHP_EOL;

// ─── 5. Priority chain — custom first, built-ins as fallback ─────────────────
//
// The most common production pattern:
//   1. Custom estimator handles proprietary models
//   2. Built-in estimators handle all standard providers
//   3. If none matches, OpenAI formula is used as a conservative fallback

$chainEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable([
        new ModelPrice('my-vision-*', inputPerMillion: 1.00, outputPerMillion: 4.00, imageInputPerMillion: 2.00),
        ...DefaultPriceCatalog::get(),
    ]),
    imageEstimators: [
        $flatRateEstimator,            // my-vision-* → 500 tokens flat
        new OpenAIImageEstimator(),    // gpt-4o, o1, o3, …
        new AnthropicImageEstimator(), // claude-*
        new GeminiImageEstimator(),    // gemini-*
    ],
);

echo "=== 5. Priority chain — custom first, built-ins as fallback ===" . PHP_EOL;
echo str_pad('Model', 30) . str_pad('Img tokens', 12) . str_pad('Img cost', 16) . 'Total' . PHP_EOL;
echo str_repeat('-', 72) . PHP_EOL;

foreach (['my-vision-pro', 'gpt-4o', 'claude-sonnet-4-6', 'gemini-2.5-flash'] as $model) {
    $r = $chainEngine->make($model)->estimateWithImages(
        text: 'Describe this image in detail.',
        images: [ImageAttachment::highDetail(1920, 1080)],
    );
    printf('%-30s %-12d $%-15.6f %s%s', $model, $r->imageTokens(), $r->imageCostUsd(), $r->format(), PHP_EOL);
}
echo PHP_EOL;

// ─── 6. Model-family wildcard — one estimator for a family ───────────────────
//
// A single estimator can handle an entire model family by prefix matching.
// For example, 'research-*' covering research-v1, research-v2, research-latest, etc.

class ResearchModelEstimator implements ImageTokenEstimatorInterface
{
    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        // Research models use a simplified patch-based formula
        $patchSize = $detail === 'low' ? 64 : 32;
        $patches   = (int) ceil($widthPx / $patchSize) * (int) ceil($heightPx / $patchSize);

        return new TokenCount(
            count: $patches,
            model: $model,
            strategy: 'research_patch_' . $patchSize,
            approximate: true,
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'research-');
    }
}

$researchEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable([
        new ModelPrice('research-*', inputPerMillion: 0.10, outputPerMillion: 0.40, imageInputPerMillion: 0.20),
    ]),
    imageEstimators: [new ResearchModelEstimator()],
);

echo "=== 6. Model-family wildcard (research-*) ===" . PHP_EOL;

foreach (['research-v1', 'research-v2', 'research-latest'] as $model) {
    $r = $researchEngine->make($model)->estimateWithImages(
        text: 'What is shown in the image?',
        images: [ImageAttachment::highDetail(1024, 768)],
    );
    printf('%-20s  %d patches as tokens  $%.6f%s', $model, $r->imageTokens(), $r->imageCostUsd(), PHP_EOL);
}
echo PHP_EOL;

// ─── 7. Full DI setup — all three axes injected together ─────────────────────
//
// Production wiring: custom text tokenizer + custom price table + custom image estimators.
// All three axes are independently injectable and composable.
//
// Wire once in your DI container and share the engine across the application.

echo "=== 7. Full DI — all three axes ===" . PHP_EOL;

$productionEngine = new PricingEngine(
    // Axis 1: text tokenizer — createDefault() loads tiktoken-php (OpenAI/Anthropic/Gemini)
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),

    // Axis 2: price table — custom prices first, default catalog as fallback
    priceTable: new ArrayPriceTable([
        new ModelPrice('my-vision-model', inputPerMillion: 1.00, outputPerMillion: 4.00, imageInputPerMillion: 2.00),
        ...DefaultPriceCatalog::get(),
    ]),

    // Axis 3: image estimators — custom first, built-ins as fallback
    imageEstimators: [
        $flatRateEstimator,            // my-vision-* proprietary formula
        new OpenAIImageEstimator(),    // gpt-4o, o1, o3-mini, …
        new AnthropicImageEstimator(), // claude-sonnet-*, claude-haiku-*, …
        new GeminiImageEstimator(),    // gemini-2.5-*, gemini-3.5-flash, …
    ],
);

$models = [
    'my-vision-model' => [ImageAttachment::highDetail(1920, 1080)],
    'gpt-4o'          => [ImageAttachment::highDetail(1920, 1080)],
    'claude-sonnet-4-6' => [ImageAttachment::highDetail(1920, 1080)],
    'gemini-2.5-flash'  => [ImageAttachment::highDetail(1920, 1080)],
];

echo str_pad('Model', 22) . str_pad('Img tokens', 12) . str_pad('Img cost', 16) . 'Total' . PHP_EOL;
echo str_repeat('-', 68) . PHP_EOL;

foreach ($models as $model => $images) {
    $r = $productionEngine->make($model)->estimateWithImages(
        text: 'Analyse this diagram and list all visible components.',
        images: $images,
    );
    printf('%-22s %-12d $%-15.6f %s%s', $model, $r->imageTokens(), $r->imageCostUsd(), $r->format(), PHP_EOL);
}
