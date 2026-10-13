<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\DTO;

use Maatify\Product\Variant\ValueObject\OptionValueSelection;

/**
 * Lifecycle and composition state of a stored Variant as read under the Product lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class VariantStateDTO
{
    /**
     * @param list<OptionValueSelection> $composition Pairs ordered by numeric Option identity.
     */
    public function __construct(
        public int $id,
        public int $productId,
        public string $fingerprint,
        public bool $active,
        public bool $deleted,
        public array $composition,
    ) {}

    public function withActive(bool $active): self
    {
        return new self($this->id, $this->productId, $this->fingerprint, $active, $this->deleted, $this->composition);
    }

    public function withDeleted(bool $deleted): self
    {
        return new self($this->id, $this->productId, $this->fingerprint, $this->active, $deleted, $this->composition);
    }
}
