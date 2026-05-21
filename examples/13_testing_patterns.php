<?php

/**
 * Example 13 — Testing Patterns
 *
 * Strategies for writing reliable tests for code that depends on PricingEngineInterface.
 *
 *   1. NullPriceTable — zero-cost engine, isUnknownModel() always true, never throws
 *   2. ArrayPriceTable with controlled prices — deterministic cost assertions
 *   3. Fake PricingEngineInterface — assert method calls without real calculation
 *   4. Testing caching logic — assert savings math with known prices
 *   5. Testing budget guards — deterministic over-budget scenarios
 *   6. isZero() and isUnknownModel() — testing the silent-failure path
 *
 * These patterns work with any PHPUnit-compatible test suite.
 * No mock library is needed — the library's value objects are enough.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\PriceTable\NullPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\PricingResult;

// ─── 1. NullPriceTable — silent zero-cost engine ──────────────────────────────
//
// NullPriceTable always returns ModelPrice::zero(). Any calculate() call returns:
//   totalCostUsd() = 0.0
//   isUnknownModel() = true
//   isZero() = true
//
// Best for: tests that exercise flow logic but don't care about dollar amounts.

echo "=== 1. NullPriceTable — zero-cost engine ===" . PHP_EOL;

$nullEngine = new PricingEngine(priceTable: new NullPriceTable());

$nullResult = $nullEngine->calculate('gpt-4o', 1_000, 500);

printf('  totalCostUsd():    %.6f  (always zero)%s', $nullResult->totalCostUsd(), PHP_EOL);
printf('  isUnknownModel():  %s      (always true)%s', $nullResult->isUnknownModel() ? 'true ' : 'false', PHP_EOL);
printf('  isZero():          %s      (always true)%s', $nullResult->isZero() ? 'true ' : 'false', PHP_EOL);
printf('  inputTokens():     %d       (tokens still tracked)%s', $nullResult->inputTokens(), PHP_EOL);
printf('  outputTokens():    %d     (tokens still tracked)%s', $nullResult->outputTokens(), PHP_EOL);
echo PHP_EOL;

// ─── 2. Controlled prices — deterministic assertions ──────────────────────────
//
// Use a hand-crafted ArrayPriceTable with simple round numbers.
// This makes the expected cost easy to calculate by hand and assert exactly.

echo "=== 2. Controlled prices — deterministic assertions ===" . PHP_EOL;

$testTable = new ArrayPriceTable([
    new ModelPrice(
        model: 'test-model',
        inputPerMillion: 1.00,    // $1.00/M = $0.000001 per token
        outputPerMillion: 2.00,   // $2.00/M = $0.000002 per token
        cacheWritePerMillion: 1.25,
        cacheReadPerMillion: 0.10,
        cacheReadIsSubsetOfInput: false,
    ),
]);

$testEngine = new PricingEngine(priceTable: $testTable);

// Basic cost — easy to verify by hand
$r1 = $testEngine->calculate('test-model', 1_000_000, 500_000);
printf('  1M input + 500K output:%s', PHP_EOL);
printf('    Expected: $1.000000 + $1.000000 = $2.000000%s', PHP_EOL);
printf('    Actual:   $%.6f%s', $r1->totalCostUsd(), PHP_EOL);

// Cache write test
$r2 = $testEngine->calculate('test-model', 0, 0, 1_000_000, 0);
printf('  1M cache write tokens:%s', PHP_EOL);
printf('    Expected: $1.250000 (1.25x surcharge)%s', PHP_EOL);
printf('    Actual:   $%.6f%s', $r2->totalCostUsd(), PHP_EOL);

// Cache read test + savings
$r3 = $testEngine->calculate('test-model', 0, 0, 0, 1_000_000);
printf('  1M cache read tokens:%s', PHP_EOL);
printf('    Expected: $0.100000 (0.10x discount)%s', PHP_EOL);
printf('    Actual:   $%.6f%s', $r3->totalCostUsd(), PHP_EOL);
printf('    Savings:  $%.6f (1M × ($1.00 - $0.10) / 1M)%s', $r3->cacheSavingsUsd(), PHP_EOL);
echo PHP_EOL;

// ─── 3. Fake PricingEngineInterface — capture what was called ─────────────────
//
// A simple fake (test double) that records every calculate() call.
// Useful when you want to assert that your service calls calculate() with the
// right parameters — without running any real pricing math.

echo "=== 3. Fake PricingEngineInterface — recording calls ===" . PHP_EOL;

class FakePricingEngine implements PricingEngineInterface
{
    /** @var list<array{model: string, input: int, output: int}> */
    public array $calls = [];

    public function calculate(
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?int $cacheWriteTokens = null,
        ?int $cacheReadTokens = null,
    ): PricingResultInterface {
        $this->calls[] = ['model' => $model, 'input' => $inputTokens, 'output' => $outputTokens];

        // Return a minimal zero-cost result — behaviour under test doesn't depend on price
        $price = ModelPrice::zero($model);
        return PricingResult::compute(price: $price, inputTokens: $inputTokens, outputTokens: $outputTokens);
    }

    public function estimate(string $text, string $model): PricingResultInterface
    {
        return PricingResult::compute(price: ModelPrice::zero($model), inputTokens: 0, outputTokens: 0);
    }

    public function estimateChat(array $messages, string $model): PricingResultInterface
    {
        return PricingResult::compute(price: ModelPrice::zero($model), inputTokens: 0, outputTokens: 0);
    }

    public function estimateWithImages(string $text, string $model, array $images): Token27\NexusAI\Pricing\ValueObject\MultimodalPricingResult
    {
        $base = PricingResult::compute(price: ModelPrice::zero($model), inputTokens: 0, outputTokens: 0);
        return new Token27\NexusAI\Pricing\ValueObject\MultimodalPricingResult($base, 0.0, 0);
    }

    public function registerPrice(ModelPrice $price): void {}
    public function getPriceFor(string $model): ModelPrice
    {
        return ModelPrice::zero($model);
    }
}

// Simulate a service that uses the fake engine
class AnalysisService
{
    public function __construct(private readonly PricingEngineInterface $pricing) {}

    public function analyse(string $text, string $model): array
    {
        // The service calls estimate + calculate — we want to assert it does both
        $estimate = $this->pricing->estimate($text, $model);
        $result   = $this->pricing->calculate($model, 500, 200);

        return ['estimate' => $estimate->totalCostUsd(), 'actual' => $result->totalCostUsd()];
    }
}

$fake    = new FakePricingEngine();
$service = new AnalysisService($fake);
$service->analyse('Test prompt', 'gpt-4o');

printf('  calculate() was called: %d time(s)%s', count($fake->calls), PHP_EOL);
if (!empty($fake->calls)) {
    printf('  First call model:  %s%s', $fake->calls[0]['model'], PHP_EOL);
    printf('  First call input:  %d tokens%s', $fake->calls[0]['input'], PHP_EOL);
    printf('  First call output: %d tokens%s', $fake->calls[0]['output'], PHP_EOL);
}
echo PHP_EOL;

// ─── 4. Testing caching logic ─────────────────────────────────────────────────
//
// Use round numbers to verify the Anthropic additive vs OpenAI subset math.

echo "=== 4. Testing caching logic (Anthropic additive vs OpenAI subset) ===" . PHP_EOL;

// Anthropic: input + cacheRead are SEPARATE token pools (not subset)
$anthropicTable = new ArrayPriceTable([
    new ModelPrice(
        model: 'test-anthropic',
        inputPerMillion: 2.00,
        outputPerMillion: 10.00,
        cacheWritePerMillion: 2.50,   // 1.25× of input
        cacheReadPerMillion: 0.20,    // 0.10× of input
        cacheReadIsSubsetOfInput: false,
    ),
]);

$anthropicEngine = new PricingEngine(priceTable: $anthropicTable);
$anthropicR = $anthropicEngine->calculate('test-anthropic', 200, 100, 0, 1_000_000);

printf('  Anthropic (additive): 1M cache read tokens%s', PHP_EOL);
printf('    cacheReadCost:   $%.6f (1M × $0.20/M)%s', $anthropicR->cacheReadCostUsd(), PHP_EOL);
printf('    cacheSavings:    $%.6f (1M × ($2.00 - $0.20) / 1M)%s', $anthropicR->cacheSavingsUsd(), PHP_EOL);

// OpenAI: cacheRead IS a subset of inputTokens
$openaiTable = new ArrayPriceTable([
    new ModelPrice(
        model: 'test-openai',
        inputPerMillion: 2.00,
        outputPerMillion: 10.00,
        cacheReadPerMillion: 1.00,    // 0.50× of input
        cacheReadIsSubsetOfInput: true,
    ),
]);

$openaiEngine = new PricingEngine(priceTable: $openaiTable);
// inputTokens = 1_200_000 (full, includes 1M cached), cacheRead = 1M (subset)
$openaiR = $openaiEngine->calculate('test-openai', 1_200_000, 100, 0, 1_000_000);

printf('  OpenAI (subset): 1.2M prompt + 1M cached subset%s', PHP_EOL);
printf(
    '    Non-cached input cost: $%.6f (200K × $2.00/M)%s',
    (200_000 / 1_000_000) * 2.00,
    PHP_EOL,
);
printf(
    '    Cached input cost:     $%.6f (1M × $1.00/M)%s',
    (1_000_000 / 1_000_000) * 1.00,
    PHP_EOL,
);
printf('    Total input cost:      $%.6f%s', $openaiR->inputCostUsd(), PHP_EOL);
printf(
    '    cacheSavings:          $%.6f (1M × ($2.00 - $1.00) / 1M)%s',
    $openaiR->cacheSavingsUsd(),
    PHP_EOL,
);
echo PHP_EOL;

// ─── 5. Testing budget guards ─────────────────────────────────────────────────
//
// Use a fixed-price engine to write deterministic over-budget assertions.
// No need to rely on real market prices that may change over time.

echo "=== 5. Testing budget guards ===" . PHP_EOL;

$budgetTable = new ArrayPriceTable([
    new ModelPrice(
        model: 'budget-model',
        inputPerMillion: 1.00,
        outputPerMillion: 1.00,
    ),
]);

$budgetEngine = new PricingEngine(priceTable: $budgetTable);

$budget     = 0.002;  // $0.002 hard cap (2000 tokens total at $1/M)
$accumulated = null;

$requests = [
    ['input' => 500,   'output' => 400,   'label' => 'Request 1 (900 tokens → $0.000900)'],
    ['input' => 600,   'output' => 400,   'label' => 'Request 2 (1000 tokens → $0.001000)'],
    ['input' => 1_000, 'output' => 500,   'label' => 'Request 3 (1500 tokens → over budget)'],
];

foreach ($requests as $req) {
    $step = $budgetEngine->calculate('budget-model', $req['input'], $req['output']);
    $projected = $accumulated === null ? $step : $accumulated->add($step);

    if ($projected->totalCostUsd() > $budget) {
        printf('  BLOCKED: %s%s', $req['label'], PHP_EOL);
        printf('    Projected: $%.6f > budget $%.6f%s', $projected->totalCostUsd(), $budget, PHP_EOL);
    } else {
        $accumulated = $projected;
        printf('  ALLOWED:  %s%s', $req['label'], PHP_EOL);
        printf(
            '    Running total: $%.6f of $%.6f%s',
            $accumulated->totalCostUsd(),
            $budget,
            PHP_EOL,
        );
    }
}
echo PHP_EOL;

// ─── 6. isZero() and isUnknownModel() — silent failure path ──────────────────
//
// When a model is not found, the engine never throws. It returns a result with:
//   isUnknownModel() = true
//   isZero() = true
//   totalCostUsd() = 0.0
//
// Test this path by checking isUnknownModel() in your assertions.

echo "=== 6. isUnknownModel() — graceful degradation assertion ===" . PHP_EOL;

$realEngine = new PricingEngine(priceTable: new ArrayPriceTable(DefaultPriceCatalog::get()));

$knownResult   = $realEngine->calculate('gpt-4o', 1_000, 500);
$unknownResult = $realEngine->calculate('completely-unknown-model-xyz', 1_000, 500);

printf('  Known model (gpt-4o):%s', PHP_EOL);
printf('    isUnknownModel(): %s%s', $knownResult->isUnknownModel() ? 'true' : 'false', PHP_EOL);
printf('    isZero():         %s%s', $knownResult->isZero() ? 'true' : 'false', PHP_EOL);
printf('    totalCostUsd():   $%.6f%s', $knownResult->totalCostUsd(), PHP_EOL);

echo PHP_EOL;

printf('  Unknown model:%s', PHP_EOL);
printf('    isUnknownModel(): %s%s', $unknownResult->isUnknownModel() ? 'true' : 'false', PHP_EOL);
printf('    isZero():         %s%s', $unknownResult->isZero() ? 'true' : 'false', PHP_EOL);
printf('    totalCostUsd():   $%.6f%s', $unknownResult->totalCostUsd(), PHP_EOL);
printf(
    '    inputTokens():    %d  (tokens still tracked even for unknown)%s',
    $unknownResult->inputTokens(),
    PHP_EOL,
);
