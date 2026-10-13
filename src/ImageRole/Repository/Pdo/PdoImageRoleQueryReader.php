<?php

declare(strict_types=1);

namespace Maatify\Product\ImageRole\Repository\Pdo;

use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\ImageRole\DTO\ImageRoleDTO;
use Maatify\Product\ImageRole\Enum\ImageRoleStatusEnum;
use Maatify\Product\ImageRole\Repository\ImageRoleQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Image Roles.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoImageRoleQueryReader implements ImageRoleQueryReaderInterface
{
    private RowHydrator $row;

    public function __construct(
        private PDO $pdo,
        ClockInterface $clock,
    ) {
        $this->row = new RowHydrator($clock);
    }

    public function findById(int $roleId): ?ImageRoleDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `role_key`, `status`, `created_at`, `updated_at`, `deleted_at` FROM `maa_product_image_roles` WHERE `id` = :id LIMIT 1',
        );
        $statement->execute(['id' => $roleId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $status = ImageRoleStatusEnum::tryFrom(RowHydrator::string($row, 'status'));
        if ($status === null) {
            throw ProductStorageFailureFactory::invalidStorageValue('status');
        }

        return new ImageRoleDTO(
            id: RowHydrator::positiveInt($row, 'id'),
            roleKey: RowHydrator::string($row, 'role_key'),
            status: $status,
            createdAt: $this->row->timestamp($row, 'created_at'),
            updatedAt: $this->row->timestamp($row, 'updated_at'),
            deletedAt: $this->row->nullableTimestamp($row, 'deleted_at'),
        );
    }
}
