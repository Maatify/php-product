<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Consumer\Service;

use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Product\DTO\ProductDTO;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Product\Repository\ProductQueryReaderInterface;

/**
 * Serves the consumer reads of Products. Reads write nothing and open no transaction.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ProductConsumerService implements ProductConsumerServiceInterface
{
    public function __construct(private ProductQueryReaderInterface $reader) {}

    public function getById(int $productId): ProductDTO
    {
        if ($productId < 1) {
            throw ProductInvalidArgumentException::nonPositiveId('productId');
        }
        $product = $this->reader->findById($productId);
        if ($product === null || $product->deletedAt !== null || $product->status !== ProductStatusEnum::ACTIVE) {
            throw ProductNotFoundException::withId($productId);
        }

        return $product;
    }
}
