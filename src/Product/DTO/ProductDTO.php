<?php

declare(strict_types=1);

namespace Maatify\Product\Product\DTO;

use JsonSerializable;
use Maatify\Product\Product\Enum\ProductStatusEnum;

/**
 * Snapshot of a stored Product.
 *
 * Timestamps are the stored `Y-m-d H:i:s` strings exactly as the Host Clock
 * produced them at write time; the package applies no timezone normalization and
 * makes no UTC claim. `deletedAt` is null while the Product is not soft deleted.
 */
final readonly class ProductDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $code,
        public ProductStatusEnum $status,
        public string $createdAt,
        public string $updatedAt,
        public ?string $deletedAt,
    ) {}

    /** @return array{id: int, code: string, status: string, createdAt: string, updatedAt: string, deletedAt: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}
