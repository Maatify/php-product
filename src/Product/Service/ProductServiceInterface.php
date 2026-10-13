<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
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
     * Both rows are written atomically: either both exist or neither does, whoever
     * owns the transaction. When the caller already has an active transaction on the
     * same connection, the operation runs in an operation-local savepoint inside it
     * and never commits or rolls back that transaction. After a failure the savepoint
     * is rolled back, so the caller's transaction holds none of this operation's
     * writes, stays active, and may still be committed or rolled back by the caller.
     * The operation is not idempotent; repeating it with the same code conflicts.
     * It performs no Pricing, Inventory, or other cross-domain work.
     *
     * @throws ProductCodeAlreadyExistsException When the code is already reserved, including by a soft-deleted Product.
     */
    public function create(CreateProductCommand $command): CreatedProductDTO;

    /**
     * Returns a consumer-visible Product: one that is active and not soft deleted.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ProductNotFoundException When no visible Product has that identity.
     */
    public function getById(int $productId): ProductDTO;

    /**
     * Returns a Product for management, whatever its status, filtered only by soft-deletion state.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ProductNotFoundException When no Product with that identity matches the deleted state.
     */
    public function getByIdForManagement(
        int $productId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): ProductDTO;
}
