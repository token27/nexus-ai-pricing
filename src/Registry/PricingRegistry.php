<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Registry;

use Closure;

use function strlen;

use Token27\NexusAI\Pricing\Catalog\DefaultPriceCatalog;
use Token27\NexusAI\Pricing\Contract\PriceTableInterface;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

/**
 * Advanced price table with glob-pattern support and lazy-loading factories.
 *
 * Resolution order (highest to lowest priority):
 *   1. Exact model ID match
 *   2. Glob pattern match — longest pattern wins (more specific beats shorter)
 *   3. ModelPrice::zero() sentinel for unknown models
 *
 * Unlike ArrayPriceTable (which stores concrete ModelPrice objects), PricingRegistry
 * also supports lazy factories for scenarios where price loading has side-effects
 * or external dependencies.
 *
 * Modeled after TokenizerRegistry for ecosystem consistency.
 *
 * @example
 *   // Use the default catalog
 *   $registry = PricingRegistry::createDefault();
 *
 *   // Override a specific model
 *   $registry->register(new ModelPrice('gpt-4o', inputPerMillion: 2.00, outputPerMillion: 8.00));
 *
 *   // Register a pattern covering all future variants
 *   $registry->register(new ModelPrice('my-llm-*', inputPerMillion: 1.50, outputPerMillion: 6.00));
 *
 *   // Lazy factory (price loaded on first access)
 *   $registry->registerFactory('remote-model', fn() => loadPriceFromDatabase('remote-model'));
 *
 *   $price = $registry->getPrice('my-llm-v3');
 *   echo $price->inputPerMillion; // 1.50
 */
final class PricingRegistry implements PriceTableInterface
{
    /**
     * @var array<string, ModelPrice> pattern/ID → resolved price (cache)
     */
    private array $resolved = [];

    /**
     * @var array<string, Closure(): ModelPrice> pattern/ID → lazy factory
     */
    private array $factories = [];

    public function __construct() {}

    /**
     * Create a registry pre-populated with all DefaultPriceCatalog entries.
     */
    public static function createDefault(): self
    {
        $registry = new self();

        foreach (DefaultPriceCatalog::get() as $price) {
            $registry->factories[$price->model] = static fn() => $price;
        }

        return $registry;
    }

    /**
     * Register a ModelPrice (eager — stored immediately in the resolved cache).
     *
     * The $price->model field is used as the registry key and may be a glob pattern.
     * Later registrations override earlier ones for the same key.
     */
    public function register(ModelPrice $price): self
    {
        $this->factories[$price->model] = static fn() => $price;
        unset($this->resolved[$price->model]);

        return $this;
    }

    /**
     * Register a lazy factory for a model/pattern.
     *
     * The factory is called only when getPrice() first resolves this pattern.
     *
     * @param Closure(): ModelPrice $factory
     */
    public function registerFactory(string $modelPattern, Closure $factory): self
    {
        $this->factories[$modelPattern] = $factory;
        unset($this->resolved[$modelPattern]);

        return $this;
    }

    // ─── PriceTableInterface ──────────────────────────────────────────────────

    public function getPrice(string $model): ModelPrice
    {
        // 1. Cached resolution
        if (isset($this->resolved[$model])) {
            return $this->resolved[$model];
        }

        // 2. Exact match in factories
        if (isset($this->factories[$model])) {
            $price = ($this->factories[$model])();
            $this->resolved[$model] = $price;

            return $price;
        }

        // 3. Glob match — longest pattern wins
        $best    = null;
        $bestLen = -1;

        foreach ($this->factories as $pattern => $factory) {
            if ($pattern !== $model && fnmatch($pattern, $model) && strlen($pattern) > $bestLen) {
                $best    = $factory;
                $bestLen = strlen($pattern);
            }
        }

        if ($best !== null) {
            $price               = ($best)()->withModel($model);
            $this->resolved[$model] = $price;

            return $price;
        }

        return ModelPrice::zero($model);
    }

    public function hasPrice(string $model): bool
    {
        if (isset($this->factories[$model])) {
            return true;
        }

        foreach (array_keys($this->factories) as $pattern) {
            if ($pattern !== $model && fnmatch($pattern, $model)) {
                return true;
            }
        }

        return false;
    }

    public function setPrice(ModelPrice $price): void
    {
        $this->register($price);
    }

    public function getKnownModels(): array
    {
        return array_keys($this->factories);
    }

    /**
     * Return any warnings accumulated during price resolution (currently unused; reserved).
     *
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return [];
    }
}
