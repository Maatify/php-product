<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Option\Exception\OptionNotFoundException;
use Maatify\Product\OptionValue\Command\CreateOptionValueCommand;
use Maatify\Product\OptionValue\DTO\CreatedOptionValueDTO;
use Maatify\Product\OptionValue\DTO\OptionValueDTO;
use Maatify\Product\OptionValue\Exception\OptionValueCodeAlreadyExistsException;
use Maatify\Product\OptionValue\Exception\OptionValueNotFoundException;

/**
 * Public application contract for Option Value creation, lifecycle, and management lookup.
 *
 * A Value's status is changed only by Product reconfiguration. Every mutation runs in the
 * savepoint transaction runner and takes the owning Product's row lock first.
 */
interface OptionValueServiceInterface
{
    /**
     * Creates an inactive Value under a non-deleted Option of a non-deleted Product; its position comes from the shared Ordering API.
     *
     * @throws OptionNotFoundException When no Option has that identity.
     * @throws ProductBusinessRuleException When the Option or its Product is soft deleted.
     * @throws OptionValueCodeAlreadyExistsException When the code is already reserved in the Option, including by a soft-deleted Value.
     */
    public function create(CreateOptionValueCommand $command): CreatedOptionValueDTO;

    /**
     * Soft-deletes a non-deleted Value whose Option and Product are not deleted. Rejected when an active
     * Variant needs it, because that would leave an invalid configuration.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws OptionValueNotFoundException When no Value has that identity.
     * @throws ProductBusinessRuleException When the Value, its Option, or its Product is soft deleted, or the deletion would violate an invariant.
     */
    public function softDelete(int $optionValueId): void;

    /**
     * Restores a soft-deleted Value with its saved status, after revalidating the configuration invariants.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws OptionValueNotFoundException When no Value has that identity.
     * @throws ProductBusinessRuleException When the Value is not soft deleted, its Option or Product is soft deleted, or the restored state would violate an invariant.
     */
    public function restore(int $optionValueId): void;

    /**
     * Returns an Option Value for management, whatever its status, filtered only by soft-deletion state.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws OptionValueNotFoundException When no Value with that identity matches the deleted state.
     */
    public function getByIdForManagement(
        int $optionValueId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): OptionValueDTO;
}
