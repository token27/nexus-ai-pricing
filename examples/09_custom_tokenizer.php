<?php

/**
 * Example 09 — Custom Tokenizer Integration
 *
 * PricingEngine accepts ANY TokenizerInterface for text counting.
 * This example covers every available mechanism:
 *
 *   1. TokenizerRegistry::register()        — eager strategy instance
 *   2. TokenizerRegistry::registerFactory() — lazy Closure factory
 *   3. TokenizerRegistry::addProvider()     — dynamic TokenizerProviderInterface
 *   4. HuggingFaceJsonStrategy              — BPE vocab from tokenizer.json file
 *   5. Custom TokenizerInterface impl       — full control, inline class
 *   6. Glob patterns                        — one entry covers a whole model family
 *   7. Warnings from suppressed load errors — getWarnings() for production logging
 *   8. Full DI wiring — tokenizer + price table + image estimators together
 *
 * For custom IMAGE token estimators see: examples/10_custom_image_estimators.php
 * All three axes (text tokenizer, price table, image estimators) are independently
 * injectable via the PricingEngine constructor.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;
use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Engine\PricingEngine;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\Tokenizer\Contract\ChatTokenCountInterface;
use Token27\Tokenizer\Contract\TokenCountInterface;
use Token27\Tokenizer\Contract\TokenizerInterface;
use Token27\Tokenizer\Contract\TokenizerProviderInterface;
use Token27\Tokenizer\Registry\TokenizerRegistry;
use Token27\Tokenizer\Strategy\CharDivisionStrategy;
use Token27\Tokenizer\Strategy\HuggingFaceJsonStrategy;
use Token27\Tokenizer\ValueObject\ChatTokenCount;
use Token27\Tokenizer\ValueObject\TokenCount;

// ─── 1. TokenizerRegistry::register() — eager strategy instance ──────────────
//
// Simplest approach: pass a ready-made TokenizerInterface instance.
// The registry caches it on first use.

$customStrategy = new class implements TokenizerInterface {
    public function count(string $text, string $model): TokenCountInterface
    {
        // Simulate a custom BPE: every word = 1 token (very rough)
        $count = max(1, str_word_count($text));
        return new TokenCount(count: $count, model: $model, strategy: 'word_split', approximate: true);
    }

    public function countChat(array $messages, string $model): ChatTokenCountInterface
    {
        $contentTokens = 0;
        foreach ($messages as $msg) {
            $contentTokens += max(1, str_word_count($msg['content'] ?? ''));
        }
        $overhead = count($messages) * 4 + 3;
        return new ChatTokenCount(
            count: $contentTokens + $overhead,
            contentTokens: $contentTokens,
            overheadTokens: $overhead,
            model: $model,
            strategy: 'word_split',
            approximate: true,
            messageCount: count($messages),
        );
    }

    public function supports(string $model): bool
    {
        return true;
    }
    public function getStrategyName(): string
    {
        return 'word_split';
    }
};

$registry = TokenizerRegistry::createDefault();
$registry->register('my-internal-model', $customStrategy);  // exact model ID
$registry->register('my-llm-*', $customStrategy);           // glob: my-llm-v1, my-llm-v2, …

$pricingEngine = PricingEngine::withTokenizer($registry);
// Also register price so it's not unknown
$pricingEngine->registerPrice(new ModelPrice('my-internal-model', inputPerMillion: 0.50, outputPerMillion: 2.00));
$pricingEngine->registerPrice(new ModelPrice('my-llm-*', inputPerMillion: 0.80, outputPerMillion: 3.20));

echo "=== 1. register() — eager strategy ===" . PHP_EOL;
$r1 = $pricingEngine->make('my-internal-model')->estimate('The quick brown fox jumps over the lazy dog');
printf('Tokens:   %d  |  Strategy: %s  |  Cost: %s%s', $r1->inputTokens(), 'word_split', $r1->format(), PHP_EOL);

$r2 = $pricingEngine->make('my-llm-v3')->estimate('Hello world this is a test prompt');
printf('Tokens:   %d  |  Glob hit: my-llm-*  |  Cost: %s%s', $r2->inputTokens(), $r2->format(), PHP_EOL);
echo PHP_EOL;

// ─── 2. TokenizerRegistry::registerFactory() — lazy Closure factory ──────────
//
// The Closure is only called when the model is first requested.
// Preferred when strategy construction is expensive (file I/O, config loading, etc.)

$lazyRegistry = TokenizerRegistry::createDefault();
$lazyRegistry->registerFactory('my-lazy-model', static function (): TokenizerInterface {
    // In a real app: load config, open a file, connect to a vocab server, etc.
    return new CharDivisionStrategy();
});

// Also: lazy factory with a glob pattern covering a whole model family
$lazyRegistry->registerFactory('enterprise-*', static function (): TokenizerInterface {
    return new CharDivisionStrategy(); // replace with your strategy
});

$lazyEngine = PricingEngine::withTokenizer($lazyRegistry);
$lazyEngine->registerPrice(new ModelPrice('my-lazy-model', inputPerMillion: 1.00, outputPerMillion: 4.00));
$lazyEngine->registerPrice(new ModelPrice('enterprise-*', inputPerMillion: 2.00, outputPerMillion: 8.00));

echo "=== 2. registerFactory() — lazy Closure ===" . PHP_EOL;
$r3 = $lazyEngine->make('my-lazy-model')->estimate('Estimate cost before sending this prompt to the API.');
printf('Tokens: %d  |  Cost: %s%s', $r3->inputTokens(), $r3->format(), PHP_EOL);

$r4 = $lazyEngine->make('enterprise-v2')->estimate('Analyse this dataset and return a summary report.');
printf('Tokens: %d  |  Glob: enterprise-*  |  Cost: %s%s', $r4->inputTokens(), $r4->format(), PHP_EOL);
echo PHP_EOL;

// ─── 3. TokenizerRegistry::addProvider() — dynamic TokenizerProviderInterface ──
//
// Providers are called AFTER static registrations fail to match.
// Use when strategy selection requires runtime logic (config lookup, model version parse, etc.)
// createFor() returns null to pass to the next provider; throws TokenizerLoadException to log + skip.

class MyModelFamilyProvider implements TokenizerProviderInterface
{
    public function createFor(string $model): ?TokenizerInterface
    {
        // Only handle "acme-*" models
        if (!str_starts_with($model, 'acme-')) {
            return null;
        }

        // In a real app: load model-specific tokenizer.json from a path derived from $model
        return new CharDivisionStrategy(); // replace with HuggingFaceJsonStrategy($path)
    }

    public function modelPatterns(): array
    {
        return ['acme-*'];  // registry uses this to skip createFor() on non-matching models
    }
}

$providerRegistry = TokenizerRegistry::createDefault();
$providerRegistry->addProvider(new MyModelFamilyProvider());

$providerEngine = PricingEngine::withTokenizer($providerRegistry);
$providerEngine->registerPrice(new ModelPrice('acme-*', inputPerMillion: 1.20, outputPerMillion: 4.80));

echo "=== 3. addProvider() — dynamic TokenizerProviderInterface ===" . PHP_EOL;
$r5 = $providerEngine->make('acme-turbo-v1')->estimate('What is the capital of France?');
printf('Tokens: %d  |  Provider: MyModelFamilyProvider  |  Cost: %s%s', $r5->inputTokens(), $r5->format(), PHP_EOL);
echo PHP_EOL;

// ─── 4. HuggingFaceJsonStrategy — BPE vocab from tokenizer.json ──────────────
//
// Loads a HuggingFace-format tokenizer.json (BPE vocabulary + merges) from disk.
// The file must already exist — no downloads are performed at runtime.
//
// How to get a tokenizer.json:
//   pip install huggingface_hub
//   python -c "from huggingface_hub import hf_hub_download; hf_hub_download('deepseek-ai/DeepSeek-V3', 'tokenizer.json')"
//
// Or download directly from the HuggingFace model page.

$tokenizerJsonPath = '/opt/models/deepseek-v3/tokenizer.json';

if (file_exists($tokenizerJsonPath)) {
    $hfRegistry = TokenizerRegistry::createDefault();
    // registerFactory() so HuggingFaceJsonStrategy is only instantiated when needed
    $hfRegistry->registerFactory('deepseek-v3*', static fn() => new HuggingFaceJsonStrategy($tokenizerJsonPath));

    $hfEngine = PricingEngine::withTokenizer($hfRegistry);
    $r6 = $hfEngine->make('deepseek-v3')->estimate('Explain quantum entanglement in simple terms.');
    printf('DeepSeek-V3 (HuggingFace BPE): %d tokens, %s%s', $r6->inputTokens(), $r6->format(), PHP_EOL);
} else {
    echo "=== 4. HuggingFaceJsonStrategy — tokenizer.json not found ===" . PHP_EOL;
    echo "Pattern (requires tokenizer.json on disk):" . PHP_EOL;
    echo '  $registry->registerFactory(\'deepseek-v3*\', fn() => new HuggingFaceJsonStrategy(\'/opt/models/deepseek-v3/tokenizer.json\'));' . PHP_EOL;
    echo '  $engine = PricingEngine::withTokenizer($registry);' . PHP_EOL;
    echo "  // → exact BPE tokenization, isApproximate() === false" . PHP_EOL;
}
echo PHP_EOL;

// ─── 5. Custom TokenizerInterface — full implementation ───────────────────────
//
// Implement TokenizerInterface directly when you have proprietary token counting
// logic: a native extension, an HTTP call to a tokenization microservice, etc.

class TiktokenProxyTokenizer implements TokenizerInterface
{
    public function __construct(
        private readonly string $endpoint, // e.g. http://localhost:8765/count
    ) {}

    public function count(string $text, string $model): TokenCountInterface
    {
        // In a real app: send $text to $this->endpoint and get back token count
        // Here we simulate with char_division as a placeholder
        $count = (int) ceil(mb_strlen($text, 'UTF-8') / 4);
        return new TokenCount(count: $count, model: $model, strategy: 'tiktoken_proxy@' . $this->endpoint, approximate: false);
    }

    public function countChat(array $messages, string $model): ChatTokenCountInterface
    {
        $contentTokens = 0;
        foreach ($messages as $msg) {
            $contentTokens += (int) ceil(mb_strlen($msg['content'] ?? '', 'UTF-8') / 4);
        }
        $overhead = count($messages) * 3 + 3;
        return new ChatTokenCount(
            count: $contentTokens + $overhead,
            contentTokens: $contentTokens,
            overheadTokens: $overhead,
            model: $model,
            strategy: 'tiktoken_proxy',
            approximate: false,
            messageCount: count($messages),
        );
    }

    public function supports(string $model): bool
    {
        return str_starts_with($model, 'gpt-');
    }
    public function getStrategyName(): string
    {
        return 'tiktoken_proxy';
    }
}

$proxyRegistry = TokenizerRegistry::createDefault();
$proxyRegistry->register('gpt-4o', new TiktokenProxyTokenizer('http://localhost:8765/count'));

$proxyEngine = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter($proxyRegistry),
    priceTable: new ArrayPriceTable(DefaultPriceCatalog::get()),
);

echo "=== 5. Custom TokenizerInterface implementation ===" . PHP_EOL;
$r7 = $proxyEngine->make('gpt-4o')->estimate('Count the tokens in this text using our custom strategy.');
printf('Tokens: %d  |  Strategy: tiktoken_proxy  |  Cost: %s%s', $r7->inputTokens(), $r7->format(), PHP_EOL);
echo PHP_EOL;

// ─── 6. Glob patterns — cover a whole model family ───────────────────────────
//
// One registered pattern covers any model ID that matches it.
// Resolution rule: exact match > longest-matching glob > providers > fallback.

$globRegistry = TokenizerRegistry::createDefault();

// Everything starting with 'my-research-' uses the same tokenizer
$globRegistry->register('my-research-*', new CharDivisionStrategy());

// More specific pattern takes priority: my-research-gpt uses a different one
$globRegistry->register('my-research-gpt-*', $customStrategy);

$globEngine = PricingEngine::withTokenizer($globRegistry);
$globEngine->registerPrice(new ModelPrice('my-research-*', inputPerMillion: 0.30, outputPerMillion: 1.20));

echo "=== 6. Glob patterns — resolution order ===" . PHP_EOL;
$r8 = $globEngine->make('my-research-llama-v2')->estimate('Test prompt for glob matching.');
printf('my-research-llama-v2 → my-research-* glob  | Tokens: %d%s', $r8->inputTokens(), PHP_EOL);

$r9 = $globEngine->make('my-research-gpt-4-clone')->estimate('Test prompt for glob matching.');
printf('my-research-gpt-4-clone → my-research-gpt-* (longer wins) | Tokens: %d%s', $r9->inputTokens(), PHP_EOL);
echo PHP_EOL;

// ─── 7. Warnings — suppressed TokenizerLoadException ─────────────────────────
//
// If a strategy raises TokenizerLoadException (missing optional dep), the registry
// logs the warning and falls back gracefully. Inspect warnings in production logs.

echo "=== 7. Inspect registry warnings ===" . PHP_EOL;

$warnRegistry = TokenizerRegistry::createDefault();
$r10 = PricingEngine::withTokenizer($warnRegistry)
    ->make('gpt-4o')
    ->estimate('A quick brown fox.');

$warnings = $warnRegistry->getWarnings();

if ($warnings === []) {
    echo 'No warnings — all strategies loaded correctly.' . PHP_EOL;
} else {
    foreach ($warnings as $warning) {
        echo 'WARN: ' . $warning . PHP_EOL;
    }
}
echo PHP_EOL;

// ─── 8. Full DI wiring — tokenizer + price table together ────────────────────
//
// Production setup: custom tokenizer AND custom price table, both injected.
// PricingEngine is shared across the app via DI container.

$productionTokenizer = TokenizerRegistry::createDefault();
$productionTokenizer->register('my-llm-v4', new CharDivisionStrategy());
$productionTokenizer->addProvider(new MyModelFamilyProvider());

$productionPricing = new PricingEngine(
    textEstimator: new TokenizerBridgeAdapter($productionTokenizer),
    priceTable: new ArrayPriceTable([
        new ModelPrice('my-llm-v4', inputPerMillion: 0.60, outputPerMillion: 2.40),
        new ModelPrice('acme-*', inputPerMillion: 1.20, outputPerMillion: 4.80),
        ...DefaultPriceCatalog::get(),
    ]),
);

echo "=== 8. Production DI — tokenizer + price table ===" . PHP_EOL;

foreach (['my-llm-v4', 'acme-ultra', 'gpt-4o', 'claude-sonnet-4-6'] as $model) {
    $r = $productionPricing->make($model)->estimate('What is 2 + 2?');
    printf('%-25s %d tokens  |  %s%s', $model . ':', $r->inputTokens(), $r->format(), PHP_EOL);
}
