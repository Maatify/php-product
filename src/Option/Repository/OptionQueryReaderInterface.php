<?php

declare(strict_types=1);

namespace Maatify\Product\Option\Repository;

use Maatify\Product\Option\DTO\OptionDTO;

/**
 * Read port for stored Options. It performs no write and holds no lock.
 *
 * @internal Not part of the Public Runtime API.
 */
interface OptionQueryReaderInterface
{
    /**
     * Finds an Option by identity whatever its status and soft-deletion state (management read).
     */
    public function findById(int $optionId): ?OptionDTO;
}
