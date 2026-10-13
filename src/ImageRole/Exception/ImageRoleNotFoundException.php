<?php

declare(strict_types=1);

namespace Maatify\Product\ImageRole\Exception;

use Maatify\Exceptions\Exception\NotFound\ResourceNotFoundMaatifyException;
use Maatify\Product\Exception\ProductExceptionInterface;

/**
 * Raised when an Image Role identity does not exist, or does not match the deleted state of the read that asked.
 */
final class ImageRoleNotFoundException extends ResourceNotFoundMaatifyException implements ProductExceptionInterface
{
    public static function withId(int $roleId): self
    {
        return new self(sprintf('Image Role [%d] was not found.', $roleId));
    }
}
