<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Common\Exception\ProductPersistenceException;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Variant\Command\UpdateVariantDisplayOrderCommand;
use Maatify\Product\Variant\Command\UpdateVariantIdentifiersCommand;
use Maatify\Product\Variant\DTO\VariantCollectionDTO;
use Maatify\Product\Variant\DTO\VariantCompositionCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\Enum\VariantStatusEnum;
use Maatify\Product\Variant\Exception\VariantBarcodeAlreadyExistsException;
use Maatify\Product\Variant\Exception\VariantNotFoundException;
use Maatify\Product\Variant\Exception\VariantSkuAlreadyExistsException;

/**
 * Public application contract for Variant reads, composition resolution, and lifecycle.
 *
 * Consumer reads return only consumer-visible Variants; management reads ignore visibility
 * and filter by status and soft-deletion state. List reads never fail for a missing Product:
 * they return an empty collection. Lifecycle mutations run in the savepoint transaction runner
 * and take the owning Product's row lock before reading the configuration they validate.
 */
interface VariantServiceInterface
{
    /**
     * Returns a consumer-visible Variant: active and not deleted, of an active and not deleted
     * Product, with a structurally valid composition, in a Product whose configuration is valid
     * as a whole (every active Value of an active Option represented by an active valid Variant).
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

    /**
     * Resolves a shopper's option choices to the one consumer-visible Variant of a Product (read-only).
     *
     * The selection must name exactly one active Value for every active Option of the Product; an
     * empty selection resolves a simple Product's Variant. It never creates or reactivates a Variant
     * and never invents a default.
     *
     * @throws ProductInvalidArgumentException When the Product identity is not positive, or the selection is structurally invalid:
     *                                         a repeated Option, an Option or Value that is not an active, non-deleted member of
     *                                         the Product, a Value paired with the wrong Option, or an active Option left unselected.
     * @throws ProductNotFoundException When the Product does not exist or is not consumer-visible.
     * @throws VariantNotFoundException When the structurally valid selection matches no consumer-visible Variant.
     * @throws ProductPersistenceException When the matching Variant's stored fingerprint does not match its composition pairs.
     */
    public function resolveByComposition(int $productId, VariantCompositionCriteriaDTO $criteria): VariantDTO;

    /**
     * Changes the status of a non-deleted Variant of a non-deleted Product. A request for the current status changes nothing.
     *
     * Activation requires the full composition to satisfy the current configuration invariants;
     * deactivation is rejected when it would leave the Product without a valid active Variant.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws VariantNotFoundException When no Variant has that identity.
     * @throws ProductBusinessRuleException When the Variant or its Product is soft deleted, or the change would violate an invariant.
     */
    public function updateStatus(int $variantId, VariantStatusEnum $status): void;

    /**
     * Soft-deletes a non-deleted Variant of a non-deleted Product; rejected when it is the last valid active Variant.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws VariantNotFoundException When no Variant has that identity.
     * @throws ProductBusinessRuleException When the Variant or its Product is soft deleted, or the deletion would violate an invariant.
     */
    public function softDelete(int $variantId): void;

    /**
     * Changes the SKU and barcode of a non-deleted Variant of a non-deleted Product; each is left
     * unchanged, set, or cleared. An inactive Variant or Product does not block it. A field set to its
     * exact stored value, or cleared while `NULL`, does not change; when no field changes nothing is
     * written. Replacing or clearing a value releases it. The Variant identity never changes.
     *
     * @throws VariantNotFoundException When no Variant has that identity.
     * @throws ProductBusinessRuleException When the Variant or its Product is soft deleted.
     * @throws VariantSkuAlreadyExistsException When another Variant holds the SKU under the collation, including a soft-deleted one.
     * @throws VariantBarcodeAlreadyExistsException When another Variant holds the barcode under the collation, including a soft-deleted one.
     */
    public function updateIdentifiers(UpdateVariantIdentifiersCommand $command): void;

    /**
     * Moves a non-deleted Variant to a position within its Product's order through the shared Ordering API: a
     * position above the current maximum is clamped to it, the current position changes nothing, and the
     * other rows of the scope shift to keep the positions contiguous. The moved row's `updated_at` is stamped.
     * Ordering never changes the configuration's validity, so no invariant is revalidated.
     *
     * @throws VariantNotFoundException When no Variant has that identity.
     * @throws ProductBusinessRuleException When the Variant or its Product is soft deleted.
     */
    public function updateDisplayOrder(UpdateVariantDisplayOrderCommand $command): void;

    /**
     * Restores a soft-deleted Variant with its saved status at the end of its Product's order, after revalidating the configuration invariants.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws VariantNotFoundException When no Variant has that identity.
     * @throws ProductBusinessRuleException When the Variant is not soft deleted, its Product is soft deleted, or the restored state would violate an invariant.
     */
    public function restore(int $variantId): void;
}
