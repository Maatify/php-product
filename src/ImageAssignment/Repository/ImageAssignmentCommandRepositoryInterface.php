<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\Repository;

use DateTimeImmutable;
use Maatify\Product\ImageAssignment\Command\CreateImageAssignmentCommand;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentIdentityDTO;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentStateDTO;
use Maatify\Product\ImageAssignment\Exception\ImageAssignmentAlreadyExistsException;
use Maatify\Product\ImageAssignment\ValueObject\ImageAssignmentScope;

/**
 * Write port for Image Assignment persistence. The calling Service owns the transaction and holds
 * the Product (and Variant, Role) locks in the stable order before calling.
 *
 * @internal Not part of the Public Runtime API.
 */
interface ImageAssignmentCommandRepositoryInterface
{
    /**
     * Inserts a non-default assignment whose position within its exact scope comes from the shared Ordering API.
     *
     * @return positive-int
     *
     * @throws ImageAssignmentAlreadyExistsException When the identity is already taken, including by a soft-deleted assignment.
     */
    public function create(CreateImageAssignmentCommand $command, ImageAssignmentScope $scope, DateTimeImmutable $occurredAt): int;

    /**
     * The immutable owner identity (Product, target Variant, Role) of an assignment, whatever its
     * state, read without a lock; null when none has that identity.
     */
    public function findIdentity(int $assignmentId): ?ImageAssignmentIdentityDTO;

    /**
     * Locks the assignment row and returns its state, or null when none has that identity.
     */
    public function lockForUpdate(int $assignmentId): ?ImageAssignmentStateDTO;

    /**
     * Clears the default marker of every other non-deleted assignment of the scope, then marks this one
     * default. The database unique key on the default scope is the final guard.
     */
    public function setDefault(int $assignmentId, string $scope, DateTimeImmutable $occurredAt): bool;

    public function clearDefault(int $assignmentId, DateTimeImmutable $occurredAt): bool;

    /**
     * Soft-deletes the assignment and clears its default marker in the same write; no other row is promoted.
     */
    public function softDelete(int $assignmentId, DateTimeImmutable $occurredAt): bool;

    /**
     * Restores the assignment as non-default, at the end of its exact scope's live order (the next
     * position of the shared Ordering API), in the same write.
     */
    public function restore(int $assignmentId, string $scope, DateTimeImmutable $occurredAt): bool;

    /**
     * Moves a non-deleted assignment within its exact scope through the shared Ordering API
     * (`moveWithinScope()`), which locks the scope, clamps a position above the maximum to it, leaves
     * a row already at the position unchanged, and stamps the moved row's `updated_at`. Returns false
     * when the assignment is not a live member of that scope.
     */
    public function updateDisplayOrder(int $assignmentId, string $scope, int $displayOrder, DateTimeImmutable $occurredAt): bool;
}
