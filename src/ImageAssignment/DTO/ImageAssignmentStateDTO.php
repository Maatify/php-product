<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\DTO;

/**
 * Lifecycle and scope state of a stored Image Assignment as read under its row lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ImageAssignmentStateDTO
{
    /**
     * @param string $scope The stored generated `ordering_scope` value.
     */
    public function __construct(
        public int $id,
        public int $productId,
        public ?int $variantId,
        public ?int $roleId,
        public string $scope,
        public bool $isDefault,
        public bool $deleted,
    ) {}
}
