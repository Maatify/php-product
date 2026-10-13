<?php

declare(strict_types=1);

namespace Maatify\Product\ImageRole\Repository;

use Maatify\Product\ImageRole\DTO\ImageRoleDTO;

/**
 * Read port for stored Image Roles. It performs no write and holds no lock.
 *
 * @internal Not part of the Public Runtime API.
 */
interface ImageRoleQueryReaderInterface
{
    /**
     * Finds an Image Role by identity whatever its status and soft-deletion state (management read).
     */
    public function findById(int $roleId): ?ImageRoleDTO;
}
