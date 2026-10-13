<?php

declare(strict_types=1);

namespace Maatify\Product\Option\DTO;

/**
 * Lifecycle state of a stored Option as read under the Product lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class OptionStateDTO
{
    /**
     * @param bool $labelled Whether a non-deleted Content record with a non-empty label exists.
     */
    public function __construct(
        public int $id,
        public int $productId,
        public bool $active,
        public bool $deleted,
        public bool $labelled,
    ) {}

    public function withActive(bool $active): self
    {
        return new self($this->id, $this->productId, $active, $this->deleted, $this->labelled);
    }

    public function withDeleted(bool $deleted): self
    {
        return new self($this->id, $this->productId, $this->active, $deleted, $this->labelled);
    }

    public function withLabelled(bool $labelled): self
    {
        return new self($this->id, $this->productId, $this->active, $this->deleted, $labelled);
    }
}
