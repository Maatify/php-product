<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\Repository;

use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentDTO;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentListCriteriaDTO;

/**
 * Read port for stored Image Assignments. It performs no write and holds no lock.
 *
 * @internal Not part of the Public Runtime API.
 */
interface ImageAssignmentQueryReaderInterface
{
    /**
     * Finds an assignment by identity whatever its soft-deletion state (management read).
     */
    public function findById(int $assignmentId): ?ImageAssignmentDTO;

    /**
     * Lists the assignments of a Product for management under the exact dimension selectors and the
     * deleted state, ordered by exact scope, display order, then identity, at most `maxResults`.
     *
     * @return list<ImageAssignmentDTO>
     */
    public function listForManagement(ImageAssignmentListCriteriaDTO $criteria): array;

    /**
     * One page of the assignments of a Product for management through the shared paginator; the counts
     * use the same filter.
     *
     * @return PageResult<ImageAssignmentDTO>
     */
    public function paginateForManagement(ImageAssignmentListCriteriaDTO $criteria, PageRequest $request): PageResult;
}
