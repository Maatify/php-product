<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\Repository\Pdo;

use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\ImageAssignment\DTO\ImageAssignmentDTO;
use Maatify\Product\ImageAssignment\Repository\ImageAssignmentQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Image Assignments.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoImageAssignmentQueryReader implements ImageAssignmentQueryReaderInterface
{
    private RowHydrator $row;

    public function __construct(
        private PDO $pdo,
        ClockInterface $clock,
    ) {
        $this->row = new RowHydrator($clock);
    }

    public function findById(int $assignmentId): ?ImageAssignmentDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `product_id`, `variant_id`, `media_asset_id`, `role_id`, `language_code`, `platform`, `is_default`, '
            . '`display_order`, `created_at`, `updated_at`, `deleted_at` FROM `maa_product_image_assignments` WHERE `id` = :id LIMIT 1',
        );
        $statement->execute(['id' => $assignmentId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new ImageAssignmentDTO(
            id: RowHydrator::positiveInt($row, 'id'),
            productId: RowHydrator::positiveInt($row, 'product_id'),
            variantId: RowHydrator::nullablePositiveInt($row, 'variant_id'),
            mediaAssetId: RowHydrator::positiveInt($row, 'media_asset_id'),
            roleId: RowHydrator::nullablePositiveInt($row, 'role_id'),
            languageCode: RowHydrator::nullableString($row, 'language_code'),
            platform: RowHydrator::nullableString($row, 'platform'),
            isDefault: RowHydrator::flag($row, 'is_default'),
            displayOrder: RowHydrator::positiveInt($row, 'display_order'),
            createdAt: $this->row->timestamp($row, 'created_at'),
            updatedAt: $this->row->timestamp($row, 'updated_at'),
            deletedAt: $this->row->nullableTimestamp($row, 'deleted_at'),
        );
    }
}
