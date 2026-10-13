<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Repository;

use Maatify\Product\Product\DTO\ProductDTO;

/**
 * Read port for stored Products.
 *
 * @internal Not part of the Public Runtime API.
 */
interface ProductQueryReaderInterface
{
    /**
     * Finds a Product by identity, whether or not it is soft deleted.
     *
     * This is a management read: it applies no status or deletion visibility rule,
     * and callers inspect {@see ProductDTO::$status} and {@see ProductDTO::$deletedAt}.
     *
     * @return ProductDTO|null Null when no Product has that identity.
     */
    public function findById(int $productId): ?ProductDTO;
}
