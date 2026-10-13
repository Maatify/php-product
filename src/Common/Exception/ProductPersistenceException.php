<?php

declare(strict_types=1);

namespace Maatify\Product\Common\Exception;

use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Maatify\Product\Exception\ProductExceptionInterface;

/**
 * Public package failure: raised when storage returns data that does not match the package
 * schema contract (documented in the Package Reference failure contract). Consumers may catch
 * it by class or through {@see ProductExceptionInterface}; the named constructors are internal.
 *
 * It reports package-owned storage-shape failures only. Unclassified database
 * failures propagate unchanged as {@see \PDOException}.
 */
final class ProductPersistenceException extends SystemMaatifyException implements ProductExceptionInterface
{
    protected function defaultErrorCode(): ErrorCodeEnum
    {
        return ErrorCodeEnum::MAATIFY_ERROR;
    }

    /**
     * AUTO_INCREMENT did not yield a positive identity for the named resource.
     */
    public static function invalidAutoIncrementIdentity(string $resource): self
    {
        return new self(sprintf('Product %s AUTO_INCREMENT did not return a valid identity.', $resource));
    }

    /**
     * A stored column holds a value the package cannot interpret.
     */
    public static function invalidStorageValue(string $column): self
    {
        return new self(sprintf('Product storage column "%s" contains an invalid value.', $column));
    }

    /**
     * A stored column was returned with an unexpected PHP type.
     */
    public static function unexpectedColumnType(string $column): self
    {
        return new self(sprintf('Product storage column "%s" has an unexpected type.', $column));
    }
}
