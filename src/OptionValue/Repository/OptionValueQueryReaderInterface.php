<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Repository;

use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\OptionValue\DTO\OptionValueDTO;
use Maatify\Product\OptionValue\DTO\OptionValueListCriteriaDTO;

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

    /**
     * Lists the Option Values of a Option for management by status and deleted state, ordered by
     * display order then identity, at most `maxResults`.
     *
     * @return list<OptionValueDTO>
     */
    public function listForManagement(int $optionId, OptionValueListCriteriaDTO $criteria): array;

    /**
     * One page of the Option Values of a Option for management through the shared paginator; the counts use the same filter.
     *
     * @return PageResult<OptionValueDTO>
     */
    public function paginateForManagement(int $optionId, OptionValueListCriteriaDTO $criteria, PageRequest $request): PageResult;
}
