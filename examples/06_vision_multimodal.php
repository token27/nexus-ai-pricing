<?php

/**
 * Example 06 — Vision / Multimodal Pricing
 *
 * When a prompt includes images the token cost depends on:
 *   - The image's pixel dimensions
 *   - The detail level ('low', 'high', or 'auto')
 *   - The provider's formula (OpenAI tiles, Anthropic w×h/750, Gemini tiles)
 *
 * estimateWithImages() uses a chain of ImageTokenEstimatorInterface instances
 * to compute pixel→token counts (first estimator whose supports($model) returns
 * true wins). The three built-in estimators cover OpenAI, Anthropic, and Gemini.
 * Custom estimators can be injected via the PricingEngine constructor — see the
 * section at the end of this file and examples/10_custom_image_estimators.php.
 *
 * Return type: MultimodalPricingResult — a superset of PricingResultInterface
 * that adds imageTokens() and imageCostUsd() while keeping all text accessors.
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

// ─── Single image — low vs high detail ────────────────────────────────────────
//
// OpenAI formula:
//   low  → always 85 tokens (flat rate, fixed thumbnail)
//   high → scale to fit 2048px, divide into 512px tiles, +85 base
//           for a 1920×1080 image: short=1024px → 4 tiles + base = 765+85 = 850 tokens

$prompt = 'Analyse the chart and summarise the key trends in three bullet points.';
$model  = 'gpt-4o';
$image  = new ImageAttachment(1920, 1080, 'high');

$highDetailResult = PricingEngine::for($model)->estimateWithImages(
    text: $prompt,
    images: [$image],
);

$lowDetailResult = PricingEngine::for($model)->estimateWithImages(
    text: $prompt,
    images: [ImageAttachment::lowDetail(1920, 1080)],
);

echo "=== gpt-4o — single image: high vs low detail ===" . PHP_EOL;
printf('High detail — image tokens: %d | image cost: $%.6f | total: %s%s', $highDetailResult->imageTokens(), $highDetailResult->imageCostUsd(), $highDetailResult->format(), PHP_EOL);
printf('Low  detail — image tokens: %d | image cost: $%.6f | total: %s%s', $lowDetailResult->imageTokens(), $lowDetailResult->imageCostUsd(), $lowDetailResult->format(), PHP_EOL);
echo PHP_EOL;

// ─── Named constructors for clarity ───────────────────────────────────────────

$screenshots = [
    ImageAttachment::highDetail(1280, 720),   // website screenshot
    ImageAttachment::highDetail(800, 600),    // smaller UI panel
    ImageAttachment::lowDetail(400, 300),     // thumbnail thumbnail
];

$multiImageResult = PricingEngine::for($model)->estimateWithImages(
    text: 'Compare these three screenshots and identify any UI inconsistencies.',
    images: $screenshots,
);

echo "=== gpt-4o — three images (2 high + 1 low) ===" . PHP_EOL;
printf('Text tokens:   %d%s', $multiImageResult->inputTokens() - $multiImageResult->imageTokens(), PHP_EOL);
printf('Image tokens:  %d%s', $multiImageResult->imageTokens(), PHP_EOL);
printf('Image cost:    $%.6f%s', $multiImageResult->imageCostUsd(), PHP_EOL);
printf('Text cost:     $%.6f%s', $multiImageResult->inputCostUsd() - $multiImageResult->imageCostUsd(), PHP_EOL);
printf('Total cost:    %s%s', $multiImageResult->format(), PHP_EOL);
echo PHP_EOL;

// ─── Auto detail mode ─────────────────────────────────────────────────────────
//
// 'auto' lets the provider decide based on image size.
// For OpenAI: images > 512px on shortest side → treated as high; else low.

$autoResult = PricingEngine::for($model)->estimateWithImages(
    text: 'What is in this image?',
    images: [ImageAttachment::auto(512, 512)],
);

echo "=== Auto detail (512×512) ===" . PHP_EOL;
printf('Image tokens:  %d%s', $autoResult->imageTokens(), PHP_EOL);
printf('Total cost:    %s%s', $autoResult->format(), PHP_EOL);
echo PHP_EOL;

// ─── Provider comparison: same image, different models ────────────────────────
//
// Each provider uses a different formula and may have a different image price.
// nexus-ai-tokenizer abstracts the per-provider formula.

$comparisonImage = ImageAttachment::highDetail(1920, 1080);
$comparisonText  = 'Describe this architectural diagram in detail.';

echo "=== Provider comparison — 1920×1080 high-detail image ===" . PHP_EOL;
echo str_pad('Model', 30) . str_pad('Img tokens', 12) . str_pad('Img cost', 14) . 'Total' . PHP_EOL;
echo str_repeat('-', 70) . PHP_EOL;

foreach (['gpt-4o', 'gpt-4o-mini', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'] as $providerModel) {
    $r = PricingEngine::for($providerModel)->estimateWithImages(
        text: $comparisonText,
        images: [$comparisonImage],
    );

    printf(
        '%-30s %-12d $%-13.6f %s%s',
        $providerModel,
        $r->imageTokens(),
        $r->imageCostUsd(),
        $r->format(),
        PHP_EOL,
    );
}

echo PHP_EOL;

// ─── toArray() for logging / serialisation ────────────────────────────────────

$logResult = PricingEngine::for('gpt-4o')->estimateWithImages(
    text: 'Describe this image.',
    images: [ImageAttachment::highDetail(1024, 768)],
);

echo "=== toArray() for structured logging ===" . PHP_EOL;

$data = $logResult->toArray();

foreach ($data as $key => $value) {
    if (is_bool($value)) {
        printf('  %-25s %s%s', $key . ':', $value ? 'true' : 'false', PHP_EOL);
    } elseif (is_float($value)) {
        printf('  %-25s %.6f%s', $key . ':', $value, PHP_EOL);
    } else {
        printf('  %-25s %s%s', $key . ':', $value, PHP_EOL);
    }
}

echo PHP_EOL;

// ─── Custom image estimators — inject your own formula ────────────────────────
//
// The three built-in estimators (OpenAI, Anthropic, Gemini) cover the major providers.
// For private/proprietary models pass your own ImageTokenEstimatorInterface list.
//
// Resolution: the first estimator whose supports($model) returns true is used.
// If none matches, the OpenAI tile formula is used as a conservative fallback.
//
// Full example: see examples/10_custom_image_estimators.php

// Flat-rate estimator: proprietary model that bills every image as exactly 500 tokens
$flatRateEstimator = new class implements ImageTokenEstimatorInterface {
    public function estimateImageTokens(int $widthPx, int $heightPx, string $detail, string $model): TokenCountInterface
    {
        return new TokenCount(count: 500, model: $model, strategy: 'flat_rate_500', approximate: false);
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'my-vision-');
    }
};

// Inject custom estimator FIRST so it takes priority, built-ins handle everything else
$customVisionEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable([
        new ModelPrice('my-vision-model', inputPerMillion: 1.00, outputPerMillion: 4.00, imageInputPerMillion: 2.00),
        ...DefaultPriceCatalog::get(),
    ]),
    imageEstimators: [
        $flatRateEstimator,       // handles my-vision-* → 500 tokens flat
        new OpenAIImageEstimator(),    // handles gpt-4o, o1, o3, …
        new AnthropicImageEstimator(), // handles claude-*
        new GeminiImageEstimator(),    // handles gemini-*
    ],
);

$customResult = $customVisionEngine->make('my-vision-model')->estimateWithImages(
    text: 'Analyse this proprietary model image.',
    images: [ImageAttachment::highDetail(1920, 1080), ImageAttachment::lowDetail(800, 600)],
);

echo "=== Custom image estimator — my-vision-model ===" . PHP_EOL;
printf('Image tokens: %d (2 images × 500 flat rate)%s', $customResult->imageTokens(), PHP_EOL);
printf('Image cost:   $%.6f%s', $customResult->imageCostUsd(), PHP_EOL);
printf('Total:        %s%s', $customResult->format(), PHP_EOL);
