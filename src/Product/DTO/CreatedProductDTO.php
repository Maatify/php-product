<?php

declare(strict_types=1);

namespace Maatify\Product\Product\DTO;

use JsonSerializable;

/**
 * Identities produced by creating a Product.
 *
 * `variantId` is the Product's single empty-composition Variant, the identity that
 * Pricing, Inventory, and order integrations refer to for a simple Product.
 */
final readonly class CreatedProductDTO implements JsonSerializable
{
    public function __construct(
        public int $productId,
        public int $variantId,
    ) {}

    /** @return array{productId: int, variantId: int} */
    public function jsonSerialize(): array
    {
        return ['productId' => $this->productId, 'variantId' => $this->variantId];
    }
}
