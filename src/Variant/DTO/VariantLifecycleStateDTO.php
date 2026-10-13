<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\DTO;

/**
 * Lifecycle state of a stored Variant as read under its row lock, for operations that target the
 * Variant as an owner (Image Assignments) or change its secondary identifiers, and need no composition.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class VariantLifecycleStateDTO
{
    public function __construct(
        public int $id,
        public int $productId,
        public bool $active,
        public bool $deleted,
        public ?string $sku = null,
        public ?string $barcode = null,
    ) {}
}
