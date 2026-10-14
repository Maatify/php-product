<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\DTO;

use JsonSerializable;

/**
 * Identity produced by creating an Image Assignment.
 */
final readonly class CreatedImageAssignmentDTO implements JsonSerializable
{
    public function __construct(public int $assignmentId) {}

    /** @return array{assignmentId: int} */
    public function jsonSerialize(): array
    {
        return ['assignmentId' => $this->assignmentId];
    }
}
