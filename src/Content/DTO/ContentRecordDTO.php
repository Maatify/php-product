<?php

declare(strict_types=1);

namespace Maatify\Product\Content\DTO;

use DateTimeImmutable;

/**
 * A stored Content record of any owner as hydrated by the read adapter; each owner's Service maps
 * it to that owner's public DTO.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ContentRecordDTO
{
    public function __construct(
        public int $id,
        public int $ownerId,
        public ?string $languageCode,
        public ContentFieldsDTO $fields,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $deletedAt,
    ) {}
}
