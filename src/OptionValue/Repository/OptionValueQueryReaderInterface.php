<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Repository;

use Maatify\Product\OptionValue\DTO\OptionValueDTO;

/**
 * Read port for stored Option Values. It performs no write and holds no lock.
 *
 * @internal Not part of the Public Runtime API.
 */
interface OptionValueQueryReaderInterface
{
    /**
     * Finds an Option Value by identity whatever its status and soft-deletion state (management read).
     */
    public function findById(int $optionValueId): ?OptionValueDTO;
}
