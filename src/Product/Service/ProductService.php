<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Service;

use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
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
 * The transaction runner owns the local transaction, or joins one that the caller
 * already holds on the same connection. Storage exceptions propagate unchanged
 * after rollback; the only conversions are the named exceptions raised by the
 * repositories.
 */
final readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        private ProductCommandRepositoryInterface $products,
        private VariantCommandRepositoryInterface $variants,
        private ProductQueryReaderInterface $reader,
        private TransactionRunnerInterface $transactions,
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
