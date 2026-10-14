<?php

declare(strict_types=1);

namespace Maatify\Product\Content\DTO;

use JsonSerializable;

/**
 * Identity produced by creating a Option Content record.
 */
final readonly class CreatedOptionContentDTO implements JsonSerializable
{
    public function __construct(public int $contentId) {}

    /** @return array{contentId: int} */
    public function jsonSerialize(): array
    {
        return ['contentId' => $this->contentId];
    }
}
