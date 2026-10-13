<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Variant\DTO\VariantCollectionDTO;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\Exception\VariantNotFoundException;

/**
 * Public application contract for Variant reads.
 *
 * Consumer reads return only consumer-visible Variants; management reads ignore visibility
 * and filter by status and soft-deletion state. List reads never fail for a missing Product:
 * they return an empty collection.
 */
interface VariantServiceInterface
{
    /**
     * Returns a consumer-visible Variant: active and not deleted, of an active and not deleted
     * Product, with a structurally valid composition.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws VariantNotFoundException When no visible Variant has that identity.
     */
    public function getById(int $variantId): VariantDTO;

    /**
     * Returns a Variant for management, whatever its status, filtered only by soft-deletion state.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws VariantNotFoundException When no Variant with that identity matches the deleted state.
     */
    public function getByIdForManagement(
        int $variantId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): VariantDTO;

    /**
     * Lists the consumer-visible Variants of a Product, at most 100, ordered by display order then identity.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     */
    public function listByProductId(
        int $productId,
        VariantVisibleListCriteriaDTO $criteria = new VariantVisibleListCriteriaDTO(),
    ): VariantCollectionDTO;

    /**
     * Lists the Variants of a Product for management, at most 100, ordered by display order then identity.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     */
    public function listByProductIdForManagement(
        int $productId,
        VariantListCriteriaDTO $criteria = new VariantListCriteriaDTO(),
    ): VariantCollectionDTO;
}
