<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Service;

use Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\DTO\CreatedProductDTO;
use Maatify\Product\Product\DTO\ProductDTO;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Product\Repository\ProductCommandRepositoryInterface;
use Maatify\Product\Product\Repository\ProductQueryReaderInterface;
use Maatify\Product\Variant\Repository\VariantCommandRepositoryInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;

/**
 * Orchestrates Product creation and lookup.
 *
 * `create()` is atomic on its own: the Product and its Variant are both written or
 * neither is. The savepoint transaction runner owns a transaction when none is active
 * and commits it on success. When the Host already holds a transaction on the same
 * connection, the runner opens an operation-local savepoint inside it and, on any
 * failure, rolls back to that savepoint before the exception propagates. So a failed
 * call leaves none of its writes in the Host transaction, which stays active, never
 * committed or rolled back here, and the Host can still commit its other work without
 * ever persisting a Product that lacks its Variant. Storage exceptions propagate
 * unchanged; the only conversions are the named exceptions raised by the repositories.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        private ProductCommandRepositoryInterface $products,
        private VariantCommandRepositoryInterface $variants,
        private ProductQueryReaderInterface $reader,
        private SavepointTransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function create(CreateProductCommand $command): CreatedProductDTO
    {
        $occurredAt = $this->clock->now();

        return $this->transactions->run(
            function () use ($command, $occurredAt): CreatedProductDTO {
                $productId = $this->products->create($command, $occurredAt);
                $variantId = $this->variants->createEmptyComposition($productId, $occurredAt);

                return new CreatedProductDTO($productId, $variantId);
            },
        );
    }

    public function getById(int $productId): ProductDTO
    {
        self::requirePositive($productId);
        $product = $this->reader->findById($productId);
        if ($product === null || $product->deletedAt !== null || $product->status !== ProductStatusEnum::ACTIVE) {
            throw ProductNotFoundException::withId($productId);
        }

        return $product;
    }

    public function getByIdForManagement(
        int $productId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): ProductDTO {
        self::requirePositive($productId);
        $product = $this->reader->findById($productId);
        $matches = $product !== null && match ($deletedState) {
            ProductDeletedStateEnum::NON_DELETED => $product->deletedAt === null,
            ProductDeletedStateEnum::DELETED_ONLY => $product->deletedAt !== null,
            ProductDeletedStateEnum::INCLUDE_DELETED => true,
        };
        if ($product === null || !$matches) {
            throw ProductNotFoundException::withId($productId);
        }

        return $product;
    }

    private static function requirePositive(int $productId): void
    {
        if ($productId < 1) {
            throw ProductInvalidArgumentException::nonPositiveId('productId');
        }
    }
}
