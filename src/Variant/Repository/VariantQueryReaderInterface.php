<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Repository;

use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;

/**
 * Read port for stored Variants. It performs no write and holds no lock.
 */
interface VariantQueryReaderInterface
{
    /**
     * Finds a Variant by identity whatever its status and soft-deletion state (management read).
     */
    public function findById(int $variantId): ?VariantDTO;

    /**
     * Finds a Variant only when it is consumer-visible.
     *
     * A Variant is visible when its Product is active and not deleted, the Variant is active
     * and not deleted, and its composition is structurally valid: every selected Option and
     * Option Value is active, not deleted, and labelled, and the composition covers exactly
     * the Product's active Options.
     */
    public function findVisibleById(int $variantId): ?VariantDTO;

    /**
     * Lists the Variants of a Product for management, filtered by status and deleted state.
     *
     * @return list<VariantDTO> Ordered by display order then identity.
     */
    public function listForManagement(int $productId, VariantListCriteriaDTO $criteria): array;

    /**
     * Lists the consumer-visible Variants of a Product (see {@see self::findVisibleById()}).
     *
     * @return list<VariantDTO> Ordered by display order then identity.
     */
    public function listVisible(int $productId, VariantVisibleListCriteriaDTO $criteria): array;
}
