<?php

declare(strict_types=1);

namespace Maatify\Product\Content\DTO;

use JsonSerializable;

/**
 * Identity produced by creating a Product Content record.
 */
final readonly class CreatedProductContentDTO implements JsonSerializable
{
    public function __construct(public int $contentId) {}

    /** @return array{contentId: int} */
    public function jsonSerialize(): array
    {
        return ['contentId' => $this->contentId];
    }
}
