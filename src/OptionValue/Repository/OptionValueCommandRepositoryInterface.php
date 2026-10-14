<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Repository;

use DateTimeImmutable;
use Maatify\Product\OptionValue\Exception\OptionValueCodeAlreadyExistsException;
use Maatify\Product\OptionValue\Management\Command\CreateOptionValueCommand;

/**
 * Write port for Option Value persistence. The calling Service owns the transaction and holds the Product lock.
 *
 * @internal Not part of the Public Runtime API.
 */
interface OptionValueCommandRepositoryInterface
{
    /**
     * Inserts an inactive Option Value whose position within its Option comes from the shared Ordering API.
     *
     * @return positive-int
     *
     * @throws OptionValueCodeAlreadyExistsException When the code is already reserved in the Option, including by a soft-deleted Value.
     */
    public function create(CreateOptionValueCommand $command, DateTimeImmutable $occurredAt): int;

    /**
     * The Product an Option Value belongs to through its Option, whatever the Value's state, or null when none has that identity.
     */
    public function findProductId(int $optionValueId): ?int;

    public function softDelete(int $optionValueId, DateTimeImmutable $occurredAt): bool;

    /**
     * Restores a soft-deleted Option Value at the end of its Option's live order (the next position of
     * the shared Ordering API), in the same write. Returns whether a row was changed.
     */
    public function restore(int $optionValueId, int $optionId, DateTimeImmutable $occurredAt): bool;

    /**
     * Moves a non-deleted row within its ordering scope through the shared Ordering API
     * (`moveWithinScope()`), which locks the scope, clamps a position above the maximum to it, leaves
     * a row already at the position unchanged, and stamps the moved row's `updated_at`. Returns false
     * when the row is not a live member of that scope.
     */
    public function updateDisplayOrder(int $optionValueId, int $optionId, int $displayOrder, DateTimeImmutable $occurredAt): bool;
}
