<?php

declare(strict_types=1);

namespace Maatify\Product\ImageRole\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\ImageRole\Command\CreateImageRoleCommand;
use Maatify\Product\ImageRole\DTO\CreatedImageRoleDTO;
use Maatify\Product\ImageRole\DTO\ImageRoleDTO;
use Maatify\Product\ImageRole\Enum\ImageRoleStatusEnum;
use Maatify\Product\ImageRole\Exception\ImageRoleKeyAlreadyExistsException;
use Maatify\Product\ImageRole\Exception\ImageRoleNotFoundException;

/**
 * Public application contract for Product Image Roles: creation, lifecycle, and management lookup.
 *
 * Role IDs are a Product-owned namespace, never Category Image Role IDs. Changing a Role's status
 * or deleting it never changes an existing assignment; consumer reads hide such assignments.
 */
interface ImageRoleServiceInterface
{
    /**
     * Creates an active Image Role.
     *
     * @throws ImageRoleKeyAlreadyExistsException When the key is already reserved, including by a soft-deleted Role.
     */
    public function create(CreateImageRoleCommand $command): CreatedImageRoleDTO;

    /**
     * Changes the status of a non-deleted Role. A request for the current status changes nothing.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageRoleNotFoundException When no Role has that identity.
     * @throws ProductBusinessRuleException When the Role is soft deleted.
     */
    public function updateStatus(int $roleId, ImageRoleStatusEnum $status): void;

    /**
     * Soft-deletes a non-deleted Role. Existing assignments are not changed.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageRoleNotFoundException When no Role has that identity.
     * @throws ProductBusinessRuleException When the Role is already soft deleted.
     */
    public function softDelete(int $roleId): void;

    /**
     * Restores a soft-deleted Role with its saved status.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageRoleNotFoundException When no Role has that identity.
     * @throws ProductBusinessRuleException When the Role is not soft deleted.
     */
    public function restore(int $roleId): void;

    /**
     * Returns a Role for management, whatever its status, filtered only by soft-deletion state.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageRoleNotFoundException When no Role with that identity matches the deleted state.
     */
    public function getByIdForManagement(
        int $roleId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): ImageRoleDTO;
}
