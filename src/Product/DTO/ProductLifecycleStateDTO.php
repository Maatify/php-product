<?php

declare(strict_types=1);

namespace Maatify\Product\Product\DTO;

/**
 * Lifecycle state of a stored Product as read under its row lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ProductLifecycleStateDTO
{
    public function __construct(
        public int $id,
        public bool $active,
        public bool $deleted,
    ) {}
}
