<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Service;

use Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\DTO\CreatedProductDTO;
use Maatify\Product\Product\DTO\ProductDTO;
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

    public function getById(int $id): ProductDTO
    {
        $product = $this->reader->findById($id);
        if ($product === null) {
            throw ProductNotFoundException::withId($id);
        }

        return $product;
    }
}
