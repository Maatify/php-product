<?php

declare(strict_types=1);

namespace Maatify\Product\Common\Exception;

use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;
use Maatify\Product\Exception\ProductExceptionInterface;

/**
 * Raised when a caller supplies an argument that violates the package input contract.
 */
final class ProductInvalidArgumentException extends InvalidArgumentMaatifyException implements ProductExceptionInterface
{
    /**
     * The field is empty or contains only whitespace.
     */
    public static function emptyField(string $field): self
    {
        return new self(sprintf('Field "%s" must not be empty or whitespace only.', $field));
    }

    /**
     * The field is longer than its documented maximum number of characters.
     */
    public static function fieldTooLong(string $field, int $maxLength): self
    {
        return new self(sprintf('Field "%s" must not exceed %d characters.', $field, $maxLength));
    }

    /**
     * The field is not valid UTF-8 text.
     */
    public static function invalidEncoding(string $field): self
    {
        return new self(sprintf('Field "%s" must be valid UTF-8 text.', $field));
    }

    /**
     * The field is not a positive integer.
     */
    public static function nonPositiveId(string $field): self
    {
        return new self(sprintf('Field "%s" must be a positive integer.', $field));
    }

    /**
     * A display order is not a positive integer.
     */
    public static function invalidDisplayOrder(int $displayOrder): self
    {
        return new self(sprintf('Display order must be a positive integer, got %d.', $displayOrder));
    }

    /**
     * A list limit is outside the allowed range.
     */
    public static function invalidListLimit(int $limit, int $maximum): self
    {
        return new self(sprintf('List maxResults must be between 1 and %d, got %d.', $maximum, $limit));
    }

    /**
     * The PDO connection does not report errors as exceptions.
     */
    public static function pdoMustThrowExceptions(): self
    {
        return new self('The PDO connection must use PDO::ERRMODE_EXCEPTION.');
    }
}
