<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\ImageAssignment\Command\CreateImageAssignmentCommand;
use Maatify\Product\ImageAssignment\DTO\CreatedImageAssignmentDTO;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentDTO;
use Maatify\Product\ImageAssignment\Exception\ImageAssignmentAlreadyExistsException;
use Maatify\Product\ImageAssignment\Exception\ImageAssignmentNotFoundException;
use Maatify\Product\ImageRole\Exception\ImageRoleNotFoundException;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Variant\Exception\VariantNotFoundException;

/**
 * Public application contract for Image Assignments: creation, the explicit default operations,
 * lifecycle, and management lookup.
 *
 * Every mutation runs in the savepoint transaction runner and locks, in this order, the Product
 * row, the target Variant row (when any), the Role row (when any), and the assignment row. Default
 * replacement is atomic within the exact `(target, role, language, platform)` scope, and the database
 * unique key on the default scope is the final guard. No operation ever promotes another assignment.
 */
interface ImageAssignmentServiceInterface
{
    /**
     * Assigns a Media Asset to a non-deleted Product, or to a non-deleted Variant of that Product, within an exact
     * scope. A role-scoped assignment needs an active, non-deleted Role. The new assignment is not the default.
     *
     * @throws ProductNotFoundException When no Product has that identity.
     * @throws VariantNotFoundException When no Variant has that identity.
     * @throws ImageRoleNotFoundException When no Role has that identity.
     * @throws ProductInvalidArgumentException When the Variant does not belong to the Product.
     * @throws ProductBusinessRuleException When the Product or Variant is soft deleted, or the Role is inactive or soft deleted.
     * @throws ImageAssignmentAlreadyExistsException When the identity exists, including as a soft-deleted assignment (restore it instead).
     */
    public function create(CreateImageAssignmentCommand $command): CreatedImageAssignmentDTO;

    /**
     * Makes a non-deleted assignment the single default of its exact scope, clearing the previous default of that scope
     * in the same operation. It requires a non-deleted target Product and Variant and a non-deleted Role when one is set;
     * an inactive Product, Variant, or Role does not block it. A request for the current default changes nothing.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageAssignmentNotFoundException When no assignment has that identity.
     * @throws ProductBusinessRuleException When the assignment, its target, or its Role is soft deleted.
     */
    public function setDefault(int $assignmentId): void;

    /**
     * Clears the default marker of a non-deleted assignment, even when its target or Role is soft deleted.
     * It promotes no other assignment and changes nothing for a non-default assignment.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageAssignmentNotFoundException When no assignment has that identity.
     * @throws ProductBusinessRuleException When the assignment is soft deleted.
     */
    public function clearDefault(int $assignmentId): void;

    /**
     * Soft-deletes a non-deleted assignment whose target Product and Variant are not deleted. A default loses its
     * marker, and no other assignment is promoted.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageAssignmentNotFoundException When no assignment has that identity.
     * @throws ProductBusinessRuleException When the assignment or its target is soft deleted.
     */
    public function softDelete(int $assignmentId): void;

    /**
     * Restores a soft-deleted assignment as non-default; its target Product and Variant must not be deleted.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageAssignmentNotFoundException When no assignment has that identity.
     * @throws ProductBusinessRuleException When the assignment is not soft deleted, or its target is soft deleted.
     */
    public function restore(int $assignmentId): void;

    /**
     * Returns an assignment for management, filtered only by soft-deletion state.
     *
     * @throws ProductInvalidArgumentException When the identity is not a positive integer.
     * @throws ImageAssignmentNotFoundException When no assignment with that identity matches the deleted state.
     */
    public function getByIdForManagement(
        int $assignmentId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): ImageAssignmentDTO;
}
