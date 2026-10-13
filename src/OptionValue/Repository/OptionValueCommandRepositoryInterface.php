<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Repository;

use DateTimeImmutable;
use Maatify\Product\OptionValue\Command\CreateOptionValueCommand;
use Maatify\Product\OptionValue\Exception\OptionValueCodeAlreadyExistsException;

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

    public function restore(int $optionValueId, DateTimeImmutable $occurredAt): bool;
}
