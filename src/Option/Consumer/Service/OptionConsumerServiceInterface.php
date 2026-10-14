<?php

declare(strict_types=1);

namespace Maatify\Product\Option\Consumer\Service;

use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Option\Consumer\Criteria\OptionVisibleListCriteriaDTO;
use Maatify\Product\Option\DTO\OptionCollectionDTO;

/**
 * Consumer contract for Options: reads only the selectable Options of a Product and writes nothing.
 */
interface OptionConsumerServiceInterface
{
    /**
     * Consumer read of the selectable Options of a Product: active, not deleted, and labelled Options of a
     * Product that has at least one consumer-visible Variant (the Variant visibility rule, including the
     * product-wide rule of `GAP-26`), ordered by display order then identity, at most `maxResults`. A
     * missing, inactive, deleted, simple, or invalidly configured Product yields an empty collection.
     *
     * @throws ProductInvalidArgumentException When the Product identity is not a positive integer.
     */
    public function listVisibleByProductId(
        int $productId,
        OptionVisibleListCriteriaDTO $criteria = new OptionVisibleListCriteriaDTO(),
    ): OptionCollectionDTO;
}
