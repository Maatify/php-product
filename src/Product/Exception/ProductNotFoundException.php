<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Exception;

use Maatify\Exceptions\Exception\NotFound\ResourceNotFoundMaatifyException;
use Maatify\Product\Exception\ProductExceptionInterface;

/**
 * Raised when a Product identity does not exist.
 */
final class ProductNotFoundException extends ResourceNotFoundMaatifyException implements ProductExceptionInterface
{
    public static function withId(int $productId): self
    {
        return new self(sprintf('Product [%d] was not found.', $productId));
    }
}
