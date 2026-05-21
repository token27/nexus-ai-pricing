<?php

/**
 * Example 08 — Dependency Injection
 *
 * For production applications you should wire PricingEngine via a DI container
 * rather than relying on the static PricingEngine::for() zero-config entry point.
 *
 * Benefits:
 *   - Type-hint against PricingEngineInterface → swap implementations in tests
 *   - Inject custom price tables (e.g. from a database or env config)
 *   - Single engine instance shared across the app (no repeated registry init)
 *   - Clean separation between pricing logic and the rest of your domain
 *
 * This example shows:
 *   1. Manual wiring (any DI approach)
 *   2. A CostCalculator service that type-hints PricingEngineInterface
 *   3. Swapping a NullPriceTable for tests
 *   4. A budget-aware RequestDispatcher pattern
 *   5. Injecting custom image estimators (all three axes: tokenizer + price table + images)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\PriceTable\ChainedPriceTable;
use Token27\NexusAI\Pricing\PriceTable\NullPriceTable;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\Tokenizer\Contract\ImageTokenEstimatorInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\ValueObject\TokenCount;
use Token27\Tokenizer\Vision\AnthropicImageEstimator;
use Token27\Tokenizer\Vision\GeminiImageEstimator;
use Token27\Tokenizer\Vision\OpenAIImageEstimator;

// ─── 1. Manual DI wiring ──────────────────────────────────────────────────────
//
// Build the engine once and inject it wherever pricing is needed.

function buildPricingEngine(): PricingEngine
{
    // Custom overrides first, default catalog as fallback
    $priceTable = new ChainedPriceTable(
        new ArrayPriceTable([
            // Override  with your negotiated enterprise rate
            new ModelPrice(
                model: 'gpt-4o',
                inputPerMillion: 2.00,
                outputPerMillion: 8.00,
                notes: 'Enterprise agreement 2026',
            ),
        ]),
        new ArrayPriceTable(DefaultPriceCatalog::get()),
    );

    return new PricingEngine(
        textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
        priceTable: $priceTable,
    );
}

$engine = buildPricingEngine();

echo "=== Manually wired PricingEngine ===" . PHP_EOL;
$result = $engine->make('gpt-4o')->calculate(inputTokens: 1_000, outputTokens: 500);
printf('gpt-4o (enterprise rate): %s%s', $result->format(), PHP_EOL);

$result2 = $engine->make('claude-sonnet-4-6')->calculate(inputTokens: 1_000, outputTokens: 500);
printf('claude-sonnet-4-6 (catalog): %s%s', $result2->format(), PHP_EOL);
echo PHP_EOL;

// ─── 2. Domain service type-hinting PricingEngineInterface ────────────────────
//
// Your application services should depend on the interface, not the concrete class.
// This makes them trivially testable with a NullPriceTable or custom stub.

class CostCalculator
{
    public function __construct(
        private readonly PricingEngineInterface $pricing,
        private readonly string                 $defaultModel = 'claude-sonnet-4-6',
    ) {}

    /**
     * Record the cost of a completed API call (post-request).
     *
     * @param array{input_tokens: int, output_tokens: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int} $usage
     */
    public function fromApiUsage(array $usage, ?string $model = null): PricingResultInterface
    {
        return $this->pricing->calculate(
            model: $model ?? $this->defaultModel,
            inputTokens: $usage['input_tokens'],
            outputTokens: $usage['output_tokens'],
            cacheWriteTokens: $usage['cache_creation_input_tokens'] ?? null,
            cacheReadTokens: $usage['cache_read_input_tokens']     ?? null,
        );
    }

    /**
     * Estimate cost before sending (pre-request budget check).
     */
    public function estimateCost(string $prompt, ?string $model = null): PricingResultInterface
    {
        return $this->pricing->estimate($prompt, $model ?? $this->defaultModel);
    }
}

// Wire the service
$calculator = new CostCalculator($engine);

// Simulate an Anthropic API usage response
$anthropicUsage = [
    'input_tokens'                  => 350,
    'output_tokens'                 => 820,
    'cache_creation_input_tokens'   => 4_800,
    'cache_read_input_tokens'       => 0,
];

echo "=== CostCalculator service ===" . PHP_EOL;
$cost = $calculator->fromApiUsage($anthropicUsage);
printf('API call cost:  %s%s', $cost->formatDetailed(), PHP_EOL);

$estimate = $calculator->estimateCost('Write a detailed blog post about PHP 8.4 features.');
printf('Pre-flight est: %s (%d tokens)%s', $estimate->format(), $estimate->inputTokens(), PHP_EOL);
echo PHP_EOL;

// ─── 3. Test isolation with NullPriceTable ────────────────────────────────────
//
// In unit tests you often don't want to assert on real dollar amounts.
// Use NullPriceTable to get a zero-cost engine that never fails.

$testEngine     = new PricingEngine(TokenizerRegistry::createDefault(), new NullPriceTable());
$testCalculator = new CostCalculator($testEngine);

$testResult = $testCalculator->fromApiUsage(['input_tokens' => 500, 'output_tokens' => 200]);

echo "=== NullPriceTable (test isolation) ===" . PHP_EOL;
printf('Unknown model: %s%s', $testResult->isUnknownModel() ? 'yes' : 'no', PHP_EOL);
printf('Zero cost:     %s%s', $testResult->isZero() ? 'yes' : 'no', PHP_EOL);
printf('Total:         $%.6f%s', $testResult->totalCostUsd(), PHP_EOL);
echo PHP_EOL;

// ─── 4. Budget-aware RequestDispatcher ────────────────────────────────────────
//
// A common production pattern: pre-flight estimate, reject if over budget,
// then compute actual cost post-request and accumulate for reporting.

class RequestDispatcher
{
    private ?PricingResultInterface $sessionCost = null;

    public function __construct(
        private readonly PricingEngineInterface $pricing,
        private readonly float                  $budgetUsd,
        private readonly string                 $model,
    ) {}

    /**
     * @param array{input_tokens: int, output_tokens: int} $usage
     */
    public function dispatch(string $prompt, array $usage): PricingResultInterface
    {
        // Pre-flight: estimate and enforce budget
        $estimate = $this->pricing->estimate($prompt, $this->model);

        if ($this->sessionCost !== null) {
            $projectedTotal = $this->sessionCost->add($estimate)->totalCostUsd();

            if ($projectedTotal > $this->budgetUsd) {
                throw new RuntimeException(sprintf(
                    'Budget exceeded: projected $%.6f > limit $%.6f',
                    $projectedTotal,
                    $this->budgetUsd,
                ));
            }
        }

        // Simulate API call → compute actual cost from usage
        $actual = $this->pricing->calculate(
            model: $this->model,
            inputTokens: $usage['input_tokens'],
            outputTokens: $usage['output_tokens'],
        );

        $this->sessionCost = $this->sessionCost === null
            ? $actual
            : $this->sessionCost->add($actual);

        return $actual;
    }

    public function sessionCost(): ?PricingResultInterface
    {
        return $this->sessionCost;
    }
}

$dispatcher = new RequestDispatcher(
    pricing: $engine,
    budgetUsd: 0.02,  // $0.02 session cap
    model: 'gpt-4o',
);

echo "=== Budget-aware RequestDispatcher ===" . PHP_EOL;

$requests = [
    ['prompt' => 'Summarise this document.',                       'usage' => ['input_tokens' => 600,   'output_tokens' => 200]],
    ['prompt' => 'Extract all entities from the previous output.', 'usage' => ['input_tokens' => 800,   'output_tokens' => 350]],
    ['prompt' => 'Generate a comprehensive 5,000-word report.',    'usage' => ['input_tokens' => 2_000, 'output_tokens' => 4_500]], // likely over budget
];

foreach ($requests as $req) {
    try {
        $r = $dispatcher->dispatch($req['prompt'], $req['usage']);
        printf('OK    — %s%s', $r->format(), PHP_EOL);
    } catch (RuntimeException $e) {
        printf('BLOCK — %s%s', $e->getMessage(), PHP_EOL);
    }
}

echo PHP_EOL;
$sessionCost = $dispatcher->sessionCost();

if ($sessionCost !== null) {
    printf('Session spent: $%.6f of $0.020000 budget%s', $sessionCost->totalCostUsd(), PHP_EOL);
}

echo PHP_EOL;

// ─── 5. Static factory alternatives in DI-friendly style ─────────────────────
//
// PricingEngine provides three static factories for quick DI bootstrapping.
// Each returns a PricingEngine (implements PricingEngineInterface) ready for injection.

$engines = [
    'withTable'    => PricingEngine::withTable(new ArrayPriceTable(DefaultPriceCatalog::get())),
    'withRegistry' => PricingEngine::withRegistry(PricingRegistry::createDefault()),
    'withTokenizer' => PricingEngine::withTokenizer(TokenizerRegistry::createDefault()),
];

echo "=== Static factory variants ===" . PHP_EOL;

foreach ($engines as $factory => $e) {
    $r = $e->make('claude-sonnet-4-6')->calculate(inputTokens: 500, outputTokens: 200);
    printf('%-15s → %s%s', $factory . '()', $r->format(), PHP_EOL);
}

echo PHP_EOL;

// ─── 5. Injecting custom image estimators ────────────────────────────────────
//
// PricingEngine accepts three independently injectable axes:
//   - tokenizer       (TokenizerInterface)        — how text tokens are counted
//   - priceTable      (PriceTableInterface)        — prices per model
//   - imageEstimators (list<ImageTokenEstimatorInterface>) — how image tokens are counted
//
// When imageEstimators is empty (default), the three built-in estimators are used:
//   OpenAIImageEstimator   → gpt-4o, o1, o3, …    (512px tile formula)
//   AnthropicImageEstimator → claude-*             (w×h / 750)
//   GeminiImageEstimator   → gemini-*              (768px tile formula)
//
// Pass a custom list to support proprietary models or override provider formulas.
// Resolution: first estimator whose supports($model) returns true wins.
//
// Full example: see examples/10_custom_image_estimators.php

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

$visionEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
    priceTable: new ArrayPriceTable([
        new ModelPrice('my-vision-model', inputPerMillion: 1.00, outputPerMillion: 4.00, imageInputPerMillion: 2.00),
        ...DefaultPriceCatalog::get(),
    ]),
    imageEstimators: [
        $flatRateEstimator,            // my-vision-* → 500 tokens flat
        new OpenAIImageEstimator(),    // gpt-4o, o1, o3, …
        new AnthropicImageEstimator(), // claude-*
        new GeminiImageEstimator(),    // gemini-*
    ],
);

echo "=== All three DI axes: tokenizer + price table + image estimators ===" . PHP_EOL;

// Custom vision model — flat rate estimator
$r1 = $visionEngine->make('my-vision-model')->estimateWithImages(
    text: 'Describe the architectural flaw visible in this diagram.',
    images: [ImageAttachment::highDetail(1920, 1080), ImageAttachment::lowDetail(800, 600)],
);
printf('my-vision-model  — image tokens: %d (2 × 500 flat)  total: %s%s', $r1->imageTokens(), $r1->format(), PHP_EOL);

// Standard model — built-in estimator still works
$r2 = $visionEngine->make('gpt-4o')->estimateWithImages(
    text: 'What is in this image?',
    images: [ImageAttachment::highDetail(1920, 1080)],
);
printf('gpt-4o           — image tokens: %d               total: %s%s', $r2->imageTokens(), $r2->format(), PHP_EOL);
