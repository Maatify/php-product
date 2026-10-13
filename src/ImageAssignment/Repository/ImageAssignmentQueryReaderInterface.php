<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\Repository;

use Maatify\Product\ImageAssignment\DTO\ImageAssignmentDTO;

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
}
