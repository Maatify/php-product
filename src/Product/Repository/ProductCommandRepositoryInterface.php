<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Repository;

use DateTimeImmutable;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\Exception\ProductCodeAlreadyExistsException;

/**
 * Write port for Product persistence.
 *
 * Implementations never open or commit a transaction; the calling Service owns the
 * transaction boundary.
 */
interface ProductCommandRepositoryInterface
{
    /**
     * Inserts a new inactive Product and returns its identity.
     *
     * @param DateTimeImmutable $occurredAt Host Clock instant stored as created and updated time.
     *
     * @return positive-int The new Product identity.
     *
     * @throws ProductCodeAlreadyExistsException When the code is already reserved, including by a soft-deleted Product.
     */
    public function create(CreateProductCommand $command, DateTimeImmutable $occurredAt): int;
}
