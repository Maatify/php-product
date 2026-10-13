<?php

declare(strict_types=1);

namespace Maatify\Product\Option\Repository;

use DateTimeImmutable;
use Maatify\Product\Option\Command\CreateOptionCommand;
use Maatify\Product\Option\DTO\OptionStateDTO;
use Maatify\Product\Option\Exception\OptionCodeAlreadyExistsException;

/**
 * Write port for Option persistence. The calling Service owns the transaction and holds the Product lock.
 *
 * @internal Not part of the Public Runtime API.
 */
interface OptionCommandRepositoryInterface
{
    /**
     * Inserts an inactive Option whose position comes from the shared Ordering API.
     *
     * @return positive-int
     *
     * @throws OptionCodeAlreadyExistsException When the code is already reserved in the Product, including by a soft-deleted Option.
     */
    public function create(CreateOptionCommand $command, DateTimeImmutable $occurredAt): int;

    /**
     * Locks the Option row and returns its state, or null when no Option has that identity.
     */
    public function lockForUpdate(int $optionId): ?OptionStateDTO;

    /**
     * The Product an Option belongs to, whatever the Option's state, or null when no Option has that identity.
     */
    public function findProductId(int $optionId): ?int;

    public function softDelete(int $optionId, DateTimeImmutable $occurredAt): bool;

    public function restore(int $optionId, DateTimeImmutable $occurredAt): bool;
}
