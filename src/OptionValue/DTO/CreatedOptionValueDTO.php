<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\DTO;

use JsonSerializable;

/**
 * Identity produced by creating an Option Value.
 */
final readonly class CreatedOptionValueDTO implements JsonSerializable
{
    public function __construct(public int $optionValueId) {}

    /** @return array{optionValueId: int} */
    public function jsonSerialize(): array
    {
        return ['optionValueId' => $this->optionValueId];
    }
}
