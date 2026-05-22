<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Engine;

use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;
use Token27\NexusAI\Pricing\Builder\PricingBuilder;
use Token27\NexusAI\Pricing\Contract\PriceTableInterface;
use Token27\NexusAI\Pricing\Contract\PricingEngineInterface;
use Token27\NexusAI\Pricing\Contract\PricingResultInterface;
use Token27\NexusAI\Pricing\Contract\TextEstimatorInterface;
use Token27\NexusAI\Pricing\Contract\UsageInterface;
use Token27\NexusAI\Pricing\Exception\EstimationNotAvailableException;
use Token27\NexusAI\Pricing\PriceTable\ArrayPriceTable;
use Token27\NexusAI\Pricing\Registry\PricingRegistry;
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;
use Token27\NexusAI\Pricing\ValueObject\MultimodalPricingResult;
use Token27\NexusAI\Pricing\ValueObject\PricingResult;

/**
 * Main pricing engine — calculates the financial cost of LLM API calls.
 *
 * ─── ZERO-CONFIG: post-request calculation (no tokenizer needed) ───────────────
 *
 *   // Calculate from token counts returned by the API:
 *   $result = PricingEngine::for('claude-sonnet-4-6')
 *       ->calculate(inputTokens: 1_200, outputTokens: 350);
 *   echo $result->totalCostUsd();  // 0.008850
 *
 *   // With Anthropic Prompt Caching:
 *   $result = PricingEngine::for('claude-sonnet-4-6')->calculate(
 *       inputTokens:      200,
 *       outputTokens:     350,
 *       cacheWriteTokens: 800,
 *       cacheReadTokens:  2_000,
 *   );
 *
 * ─── PRE-REQUEST ESTIMATION (requires TextEstimatorInterface) ─────────────────
 *
 *   // With token27/nexus-ai-tokenizer installed:
 *   use Token27\NexusAI\Pricing\Adapter\TokenizerBridgeAdapter;
 *   use Token27\Tokenizer\Registry\TokenizerRegistry;
 *
 *   $engine = new PricingEngine(
 *       textEstimator: new TokenizerBridgeAdapter(TokenizerRegistry::createDefault()),
 *   );
 *   $result = $engine->estimate('Write a PHP function', 'gpt-4o');
 *
 *   // Or via PricingEngine::withTokenizer() factory:
 *   $result = PricingEngine::withTokenizer(TokenizerRegistry::createDefault())
 *       ->make('gpt-4o')
 *       ->estimate('Write a PHP function');
 *
 * ─── CUSTOM PRICE TABLE ────────────────────────────────────────────────────────
 *
 *   PricingEngine::withTable(new JsonFilePriceTable('/etc/prices.json'))
 *       ->make('my-private-model')
 *       ->calculate(inputTokens: 500, outputTokens: 100);
 *
 * ─── DEPENDENCY INJECTION (recommended for production) ─────────────────────────
 *
 *   $engine = new PricingEngine(
 *       textEstimator: new TokenizerBridgeAdapter($myTokenizer),  // optional
 *       priceTable:    new ChainedPriceTable(
 *           new JsonFilePriceTable('/etc/myapp/prices.json'),
 *           new ArrayPriceTable(DefaultPriceCatalog::get()),
 *       ),
 *   );
 *   $result = $engine->calculate('gpt-4o', 1200, 350);
 *
 * ─── ACCUMULATE COSTS ACROSS TOOL-CALLING LOOPS ───────────────────────────────
 *
 *   $total = $step1->add($step2)->add($step3);
 *   echo $total->totalCostUsd();
 */
final class PricingEngine implements PricingEngineInterface
{
    /**
     * @var list<\Token27\Tokenizer\Contract\ImageTokenEstimatorInterface> Image token estimators (from token27/nexus-ai-tokenizer or custom).
     */
    private readonly array $imageEstimators;

    /**
     * @param TextEstimatorInterface|null $textEstimator
     *        Token counter for pre-request estimation (estimate() / estimateChat()).
     *        NOT required for calculate() — the API already returns exact token counts.
     *        When null, calling estimate() or estimateChat() throws EstimationNotAvailableException.
     *
     * @param PriceTableInterface $priceTable
     *        Price lookup table. Defaults to an empty ArrayPriceTable.
     *        Use DefaultPriceCatalog::get() or PricingRegistry::createDefault() for built-in prices.
     *
     * @param list<\Token27\Tokenizer\Contract\ImageTokenEstimatorInterface> $imageEstimators
     *        Image token estimators for estimateWithImages(). When empty (default),
     *        the three built-in estimators from token27/nexus-ai-tokenizer are used if that
     *        package is installed (OpenAI tile formula, Anthropic w×h/750, Gemini tile formula).
     *        If the tokenizer package is not installed, no image estimators are used by default —
     *        pass your own implementations here.
     */
    public function __construct(
        private readonly ?TextEstimatorInterface $textEstimator = null,
        private readonly PriceTableInterface $priceTable = new ArrayPriceTable([]),
        array $imageEstimators = [],
    ) {
        $this->imageEstimators = $imageEstimators;
    }

    // ─── Static Entry Points ──────────────────────────────────────────────────

    /**
     * Zero-config entry point — creates a default engine and binds it to $model.
     *
     * Uses DefaultPriceCatalog prices. No text estimator is configured by default —
     * call calculate() directly, or configure an estimator if you need estimate().
     *
     *   $result = PricingEngine::for('gpt-4o')->calculate(inputTokens: 1200, outputTokens: 350);
     */
    public static function for(string $model): PricingBuilder
    {
        return new PricingBuilder($model, self::createDefault());
    }

    /**
     * Create an engine with a custom price table. No text estimator is configured.
     *
     * calculate() works immediately. For estimate(), inject a TextEstimatorInterface.
     *
     *   PricingEngine::withTable(new JsonFilePriceTable('/etc/prices.json'))
     *       ->make('my-model')
     *       ->calculate(500, 100);
     */
    public static function withTable(PriceTableInterface $priceTable): self
    {
        return new self(
            textEstimator: null,
            priceTable: $priceTable,
        );
    }

    /**
     * Create an engine with a custom PricingRegistry. No text estimator is configured.
     */
    public static function withRegistry(PricingRegistry $registry): self
    {
        return new self(
            textEstimator: null,
            priceTable: $registry,
        );
    }

    /**
     * Create an engine with a TextEstimatorInterface for pre-request estimation.
     *
     *   $engine = PricingEngine::withTextEstimator(new MyEstimator());
     *   $result = $engine->make('gpt-4o')->estimate('Hello world');
     */
    public static function withTextEstimator(TextEstimatorInterface $estimator): self
    {
        return new self(
            textEstimator: $estimator,
            priceTable: PricingRegistry::createDefault(),
        );
    }

    /**
     * Create an engine bridged to a token27/nexus-ai-tokenizer TokenizerInterface.
     *
     * Requires token27/nexus-ai-tokenizer to be installed.
     *
     *   use Token27\Tokenizer\Registry\TokenizerRegistry;
     *
     *   $engine = PricingEngine::withTokenizer(TokenizerRegistry::createDefault());
     *   $result = $engine->make('gpt-4o')->estimate('Hello world');
     *
     * @param \Token27\Tokenizer\Contract\TokenizerInterface $tokenizer
     */
    public static function withTokenizer(object $tokenizer): self
    {
        return new self(
            textEstimator: new TokenizerBridgeAdapter($tokenizer),
            priceTable: PricingRegistry::createDefault(),
        );
    }

    // ─── Instance entry point ─────────────────────────────────────────────────

    /**
     * Bind this engine to a model and return a PricingBuilder for fluent calls.
     *
     * Named `make()` (not `for()`) because PHP does not allow the same method
     * name to be both static and non-static.
     *
     *   $engine = PricingEngine::withTable($myTable);
     *   $result = $engine->make('gpt-4o')->calculate(500, 100);
     */
    public function make(string $model): PricingBuilder
    {
        return new PricingBuilder($model, $this);
    }

    // ─── PricingEngineInterface ────────────────────────────────────────────────

    /**
     * @throws EstimationNotAvailableException if no TextEstimatorInterface is configured.
     */
    public function estimate(string $text, string $model): PricingResultInterface
    {
        if ($this->textEstimator === null) {
            throw new EstimationNotAvailableException();
        }

        $tokenCount = $this->textEstimator->estimateTokenCount($text, $model);
        $price = $this->priceTable->getPrice($model);

        return PricingResult::compute(
            price: $price,
            inputTokens: $tokenCount,
            outputTokens: 0,
            unknownModel: !$this->priceTable->hasPrice($model),
        );
    }

    /**
     * @throws EstimationNotAvailableException if no TextEstimatorInterface is configured.
     */
    public function estimateChat(array $messages, string $model): PricingResultInterface
    {
        if ($this->textEstimator === null) {
            throw new EstimationNotAvailableException();
        }

        $tokenCount = $this->textEstimator->estimateChatTokenCount($messages, $model);
        $price = $this->priceTable->getPrice($model);

        return PricingResult::compute(
            price: $price,
            inputTokens: $tokenCount,
            outputTokens: 0,
            unknownModel: !$this->priceTable->hasPrice($model),
        );
    }

    public function calculate(
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?int $cacheWriteTokens = null,
        ?int $cacheReadTokens = null,
    ): PricingResultInterface {
        $price = $this->priceTable->getPrice($model);

        return PricingResult::compute(
            price: $price,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cacheWriteTokens: $cacheWriteTokens ?? 0,
            cacheReadTokens: $cacheReadTokens ?? 0,
            unknownModel: !$this->priceTable->hasPrice($model),
        );
    }

    public function calculateFromUsage(UsageInterface $usage, string $model): PricingResultInterface
    {
        $price = $this->priceTable->getPrice($model);

        // Texto input
        $textInputCost = ($usage->textInputTokens() / 1_000_000) * $price->inputPerMillion;

        // Imagen input (vision)
        $imageInputCost = 0.0;
        if ($usage->imageInputTokens() > 0) {
            $rate = $price->imageInputPerMillion ?? $price->inputPerMillion;
            $imageInputCost = ($usage->imageInputTokens() / 1_000_000) * $rate;
        }

        // Texto output
        $textOutputCost = ($usage->textOutputTokens() / 1_000_000) * $price->outputPerMillion;

        // Imagen output (generation) — NUEVO
        $imageOutputCost = 0.0;
        if ($usage->imageOutputTokens() > 0) {
            $rate = $price->imageOutputPerMillion ?? $price->outputPerMillion;
            $imageOutputCost = ($usage->imageOutputTokens() / 1_000_000) * $rate;
        }

        // Cache (sin cambios)
        $cacheWriteCost = 0.0;
        $cacheReadCost = 0.0;
        $cacheSavings = 0.0;

        if ($usage->cacheWriteTokens() > 0 && $price->cacheWritePerMillion !== null) {
            $cacheWriteCost = ($usage->cacheWriteTokens() / 1_000_000) * $price->cacheWritePerMillion;
        }
        if ($usage->cacheReadTokens() > 0 && $price->cacheReadPerMillion !== null) {
            $cacheReadCost = ($usage->cacheReadTokens() / 1_000_000) * $price->cacheReadPerMillion;
            if ($price->cacheReadIsSubsetOfInput) {
                $cacheSavings = ($usage->cacheReadTokens() / 1_000_000)
                    * ($price->inputPerMillion - $price->cacheReadPerMillion);
            }
        }

        return new PricingResult(
            model: $model,
            inputCostUsd: $textInputCost + $imageInputCost,
            outputCostUsd: $textOutputCost,
            cacheWriteCostUsd: $cacheWriteCost,
            cacheReadCostUsd: $cacheReadCost,
            cacheSavingsUsd: $cacheSavings,
            inputTokens: $usage->totalInputTokens(),
            outputTokens: $usage->totalOutputTokens(),
            cacheWriteTokens: $usage->cacheWriteTokens() ?? 0,
            cacheReadTokens: $usage->cacheReadTokens() ?? 0,
            currency: $price->currency,
            isUnknownModel: false,
            imageOutputCostUsd: $imageOutputCost,
            imageOutputTokens: $usage->imageOutputTokens(),
            imageCount: 0,
        );
    }

    /**
     * @throws EstimationNotAvailableException if no TextEstimatorInterface is configured.
     */
    public function estimateWithImages(
        string $text,
        string $model,
        array $images,
    ): MultimodalPricingResult {
        $textResult = $this->estimate($text, $model);

        $imageTokens = 0;

        foreach ($images as $image) {
            if (!$image instanceof ImageAttachment) {
                continue;
            }

            $imageTokens += $this->countImageTokens($image, $model);
        }

        $price = $this->priceTable->getPrice($model);
        $imageCost = ($imageTokens / 1_000_000) * $price->effectiveImagePrice();

        return new MultimodalPricingResult(
            textResult: $textResult,
            imageCostUsd: $imageCost,
            imageTokens: $imageTokens,
        );
    }

    public function registerPrice(ModelPrice $price): void
    {
        $this->priceTable->setPrice($price);
    }

    public function getPriceFor(string $model): ModelPrice
    {
        return $this->priceTable->getPrice($model);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * Resolve the token count for a single image using the injected estimator chain.
     *
     * Falls back to the OpenAI tile formula if none matches.
     * If no image estimators are available (no injected and tokenizer not installed),
     * returns 0 — image tokens are not counted.
     */
    private function countImageTokens(ImageAttachment $image, string $model): int
    {
        foreach ($this->getImageEstimators() as $estimator) {
            /** @var \Token27\Tokenizer\Contract\ImageTokenEstimatorInterface $estimator */
            if ($estimator->supports($model)) {
                return $estimator->estimateImageTokens(
                    $image->widthPx,
                    $image->heightPx,
                    $image->detail,
                    $model,
                )->count();
            }
        }

        // No matching estimator found — return 0 rather than throwing
        return 0;
    }

    /**
     * Return the active image estimator chain.
     *
     * When custom estimators were injected, uses those.
     * Otherwise, tries to use the built-in estimators from token27/nexus-ai-tokenizer
     * if that package is installed. Returns empty array if neither is available.
     *
     * @return list<\Token27\Tokenizer\Contract\ImageTokenEstimatorInterface>
     */
    private function getImageEstimators(): array
    {
        if ($this->imageEstimators !== []) {
            return $this->imageEstimators;
        }

        if (!class_exists(\Token27\Tokenizer\Vision\OpenAIImageEstimator::class)) {
            return [];
        }

        return [
            new \Token27\Tokenizer\Vision\OpenAIImageEstimator(),
            new \Token27\Tokenizer\Vision\AnthropicImageEstimator(),
            new \Token27\Tokenizer\Vision\GeminiImageEstimator(),
        ];
    }

    private static function createDefault(): self
    {
        return new self(
            textEstimator: null,
            priceTable: PricingRegistry::createDefault(),
        );
    }
}
