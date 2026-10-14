<?php

declare(strict_types=1);

namespace Maatify\Product\ImageRole\DTO;

use JsonSerializable;

/**
 * Identity produced by creating a Product Image Role.
 */
final readonly class CreatedImageRoleDTO implements JsonSerializable
{
    public function __construct(public int $roleId) {}

    /** @return array{roleId: int} */
    public function jsonSerialize(): array
    {
        return ['roleId' => $this->roleId];
    }
}
