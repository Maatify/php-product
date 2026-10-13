<?php

declare(strict_types=1);

namespace Maatify\Product\Option\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Option\Command\CreateOptionCommand;
use Maatify\Product\Option\Command\UpdateOptionDisplayOrderCommand;
use Maatify\Product\Option\DTO\CreatedOptionDTO;
use Maatify\Product\Option\DTO\OptionDTO;
use Maatify\Product\Option\Exception\OptionCodeAlreadyExistsException;
use Maatify\Product\Option\Exception\OptionNotFoundException;
use Maatify\Product\Product\Exception\ProductNotFoundException;

/**
 * Public application contract for Option creation, lifecycle, and management lookup.
 *
 * An Option's status is changed only by Product reconfiguration. Every mutation runs in the
 * savepoint transaction runner and takes the owning Product's row lock first.
 */
interface OptionServiceInterface
{
    /**
     * Creates an inactive Option under a non-deleted Product; its position comes from the shared Ordering API.
     *
     * @throws ProductNotFoundException When no Product has that identity.
     * @throws ProductBusinessRuleException When the Product is soft deleted.
     * @throws OptionCodeAlreadyExistsException When the code is already reserved in the Product, including by a soft-deleted Option.
     */
    public function create(CreateOptionCommand $command): CreatedOptionDTO;

    /**
     * Soft-deletes a non-deleted Option of a non-deleted Product. Its Values are not changed.
     * Rejected when the Option is part of the active configuration, because that would leave an
     * invalid configuration; reconfiguration deactivates it first.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws OptionNotFoundException When no Option has that identity.
     * @throws ProductBusinessRuleException When the Option or its Product is soft deleted, or the deletion would violate an invariant.
     */
    public function softDelete(int $optionId): void;

    /**
     * Moves a non-deleted Option to a position within its Product's order through the shared Ordering API: a
     * position above the current maximum is clamped to it, the current position changes nothing, and the
     * other rows of the scope shift to keep the positions contiguous. The moved row's `updated_at` is stamped.
     * Ordering never changes the configuration's validity, so no invariant is revalidated.
     *
     * @throws OptionNotFoundException When no Option has that identity.
     * @throws ProductBusinessRuleException When the Option or its Product is soft deleted.
     */
    public function updateDisplayOrder(UpdateOptionDisplayOrderCommand $command): void;

    /**
     * Restores a soft-deleted Option with its saved status at the end of its Product's order, after revalidating the configuration invariants.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws OptionNotFoundException When no Option has that identity.
     * @throws ProductBusinessRuleException When the Option is not soft deleted, its Product is soft deleted, or the restored state would violate an invariant.
     */
    public function restore(int $optionId): void;

    /**
     * Returns an Option for management, whatever its status, filtered only by soft-deletion state.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws OptionNotFoundException When no Option with that identity matches the deleted state.
     */
    public function getByIdForManagement(
        int $optionId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): OptionDTO;
}
