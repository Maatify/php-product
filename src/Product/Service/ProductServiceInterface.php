<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Service;

use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\DTO\CreatedProductDTO;
use Maatify\Product\Product\DTO\ProductDTO;
use Maatify\Product\Product\Exception\ProductCodeAlreadyExistsException;
use Maatify\Product\Product\Exception\ProductNotFoundException;

/**
 * Public application contract for Product creation and lookup.
 */
interface ProductServiceInterface
{
    /**
     * Creates an inactive Product together with its single active empty-composition Variant.
     *
     * Both rows are written in one local transaction: either both exist or neither
     * does. When the caller already has an active transaction on the same
     * connection, the operation joins it and the caller decides commit or rollback.
     * The operation is not idempotent; repeating it with the same code conflicts.
     * It performs no Pricing, Inventory, or other cross-domain work.
     *
     * @throws ProductCodeAlreadyExistsException When the code is already reserved, including by a soft-deleted Product.
     */
    public function create(CreateProductCommand $command): CreatedProductDTO;

    /**
     * Returns a Product by identity, including inactive and soft-deleted ones.
     *
     * @throws ProductNotFoundException When no Product has that identity.
     */
    public function getById(int $id): ProductDTO;
}
