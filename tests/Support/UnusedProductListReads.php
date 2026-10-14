<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Support;

use LogicException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\Product\DTO\ProductDTO;
use Maatify\Product\Product\Management\Criteria\ProductListCriteriaDTO;

/**
 * Code lookup and list reads of {@see \Maatify\Product\Product\Repository\ProductQueryReaderInterface}
 * for test doubles that only exercise identity reads; calling one is a test error.
 */
trait UnusedProductListReads
{
    public function findByCode(string $code): ?ProductDTO
    {
        throw new LogicException('findByCode is not expected in this test.');
    }

    public function listForManagement(ProductListCriteriaDTO $criteria): array
    {
        throw new LogicException('listForManagement is not expected in this test.');
    }

    public function paginateForManagement(ProductListCriteriaDTO $criteria, PageRequest $request): PageResult
    {
        throw new LogicException('paginateForManagement is not expected in this test.');
    }
}
