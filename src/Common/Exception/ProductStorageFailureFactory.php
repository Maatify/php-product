<?php

declare(strict_types=1);

namespace Maatify\Product\Common\Exception;

/**
 * Builds the package's {@see ProductPersistenceException} for its own storage adapters.
 *
 * Internal: this class is not part of the Public Runtime API. Consumers depend on the
 * exception type and on the {@see \Maatify\Product\Exception\ProductExceptionInterface}
 * marker only; the exception itself exposes no named constructors.
 *
 * @internal
 */
final class ProductStorageFailureFactory
{
    private function __construct() {}

    /**
     * AUTO_INCREMENT did not yield a positive identity for the named resource.
     */
    public static function invalidAutoIncrementIdentity(string $resource): ProductPersistenceException
    {
        return new ProductPersistenceException(
            sprintf('Product %s AUTO_INCREMENT did not return a valid identity.', $resource),
        );
    }

    /**
     * A stored column holds a value the package cannot interpret.
     */
    public static function invalidStorageValue(string $column): ProductPersistenceException
    {
        return new ProductPersistenceException(
            sprintf('Product storage column "%s" contains an invalid value.', $column),
        );
    }

    /**
     * A stored column was returned with an unexpected PHP type.
     */
    public static function unexpectedColumnType(string $column): ProductPersistenceException
    {
        return new ProductPersistenceException(
            sprintf('Product storage column "%s" has an unexpected type.', $column),
        );
    }
}
