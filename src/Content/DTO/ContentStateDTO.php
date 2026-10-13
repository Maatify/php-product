<?php

declare(strict_types=1);

namespace Maatify\Product\Content\DTO;

/**
 * State of a stored Content record as read under its row lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ContentStateDTO
{
    public function __construct(
        public int $id,
        public int $ownerId,
        public ContentFieldsDTO $fields,
        public bool $deleted,
    ) {}
}
