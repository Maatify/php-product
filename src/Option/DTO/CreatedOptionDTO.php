<?php

declare(strict_types=1);

namespace Maatify\Product\Option\DTO;

use JsonSerializable;

/**
 * Identity produced by creating an Option.
 */
final readonly class CreatedOptionDTO implements JsonSerializable
{
    public function __construct(public int $optionId) {}

    /** @return array{optionId: int} */
    public function jsonSerialize(): array
    {
        return ['optionId' => $this->optionId];
    }
}
