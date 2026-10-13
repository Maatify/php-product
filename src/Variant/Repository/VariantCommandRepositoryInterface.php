<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Repository;

use DateTimeImmutable;

/**
 * Write port for Variant persistence.
 *
 * Implementations never open or commit a transaction; the calling Service owns the
 * transaction boundary and must already hold one.
 *
 * @internal Not part of the Public Runtime API.
 */
interface VariantCommandRepositoryInterface
{
    /**
     * Creates the active empty-composition Variant of an existing Product.
     *
     * The Product row is locked first so concurrent writers to the same Product
     * serialize, and the display order is allocated through the shared Ordering API.
     *
     * @param positive-int $productId Existing Product identity.
     * @param DateTimeImmutable $occurredAt Host Clock instant stored as created and updated time.
     *
     * @return positive-int The new Variant identity.
     */
    public function createEmptyComposition(int $productId, DateTimeImmutable $occurredAt): int;
}
