<?php

declare(strict_types=1);

namespace Token27\NexusAI\Pricing\Exception;

use RuntimeException;

/**
 * Thrown when a PriceTable cannot be initialized or read.
 *
 * Common causes:
 *   - JsonFilePriceTable: file does not exist, is not readable, or contains invalid JSON
 *   - ChainedPriceTable: all sub-tables failed to initialize
 */
final class PriceTableException extends RuntimeException
{
    public static function fileNotFound(string $path): self
    {
        return new self("Price table file not found or not readable: '{$path}'");
    }

    public static function invalidJson(string $path, string $detail): self
    {
        return new self("Invalid JSON in price table file '{$path}': {$detail}");
    }

    public static function invalidEntry(string $path, int $index, string $detail): self
    {
        return new self("Invalid price entry at index {$index} in '{$path}': {$detail}");
    }
}
