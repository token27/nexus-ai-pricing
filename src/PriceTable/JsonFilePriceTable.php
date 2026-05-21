<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\PriceTable;

use function is_array;
use function is_string;

use Token27\NexusAI\Pricing\Contract\PriceTableInterface;
use Token27\NexusAI\Pricing\Exception\PriceTableException;
use Token27\NexusAI\Pricing\ValueObject\ModelPrice;

/**
 * Price table loaded from a JSON file — update prices without a code deploy.
 *
 * Prices are loaded lazily on first access and cached in memory for the lifetime
 * of the object. Create a new instance if you need to reload an updated file.
 *
 * JSON format — array of objects, all fields except `model` are optional:
 *
 * @example JSON file:
 *   [
 *     {
 *       "model": "my-private-llm-v3",
 *       "input_per_million": 1.50,
 *       "output_per_million": 5.00,
 *       "cache_write_per_million": 1.875,
 *       "cache_read_per_million": 0.15,
 *       "cache_read_is_subset_of_input": false,
 *       "image_input_per_million": null,
 *       "currency": "USD",
 *       "notes": "Internal deployment — verified 2026-05-20"
 *     }
 *   ]
 *
 * @example PHP:
 *   $table = new JsonFilePriceTable('/etc/myapp/ai-prices.json');
 *   $price = $table->getPrice('my-private-llm-v3');
 */
final class JsonFilePriceTable implements PriceTableInterface
{
    private ?ArrayPriceTable $inner = null;

    public function __construct(
        private readonly string $filePath,
    ) {}

    public function getPrice(string $model): ModelPrice
    {
        return $this->inner()->getPrice($model);
    }

    public function hasPrice(string $model): bool
    {
        return $this->inner()->hasPrice($model);
    }

    public function setPrice(ModelPrice $price): void
    {
        $this->inner()->setPrice($price);
    }

    public function getKnownModels(): array
    {
        return $this->inner()->getKnownModels();
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function inner(): ArrayPriceTable
    {
        if ($this->inner === null) {
            $this->inner = $this->load();
        }

        return $this->inner;
    }

    private function load(): ArrayPriceTable
    {
        if (!file_exists($this->filePath) || !is_readable($this->filePath)) {
            throw PriceTableException::fileNotFound($this->filePath);
        }

        $content = file_get_contents($this->filePath);

        if ($content === false) {
            throw PriceTableException::fileNotFound($this->filePath);
        }

        /** @var mixed $data */
        $data = json_decode($content, associative: true);

        if (!is_array($data)) {
            throw PriceTableException::invalidJson($this->filePath, json_last_error_msg());
        }

        $prices = [];

        foreach ($data as $index => $entry) {
            if (!is_array($entry)) {
                throw PriceTableException::invalidEntry($this->filePath, (int) $index, 'entry must be an object');
            }

            if (empty($entry['model']) || !is_string($entry['model'])) {
                throw PriceTableException::invalidEntry($this->filePath, (int) $index, '"model" field is required and must be a string');
            }

            if (!isset($entry['input_per_million']) || !is_numeric($entry['input_per_million'])) {
                throw PriceTableException::invalidEntry($this->filePath, (int) $index, '"input_per_million" is required and must be numeric');
            }

            if (!isset($entry['output_per_million']) || !is_numeric($entry['output_per_million'])) {
                throw PriceTableException::invalidEntry($this->filePath, (int) $index, '"output_per_million" is required and must be numeric');
            }

            $prices[] = new ModelPrice(
                model: $entry['model'],
                inputPerMillion: (float) $entry['input_per_million'],
                outputPerMillion: (float) $entry['output_per_million'],
                cacheWritePerMillion: isset($entry['cache_write_per_million']) ? (float) $entry['cache_write_per_million'] : null,
                cacheReadPerMillion: isset($entry['cache_read_per_million']) ? (float) $entry['cache_read_per_million'] : null,
                cacheReadIsSubsetOfInput: (bool) ($entry['cache_read_is_subset_of_input'] ?? false),
                imageInputPerMillion: isset($entry['image_input_per_million']) ? (float) $entry['image_input_per_million'] : null,
                currency: (string) ($entry['currency'] ?? 'USD'),
                notes: isset($entry['notes']) ? (string) $entry['notes'] : null,
            );
        }

        return new ArrayPriceTable($prices);
    }
}
