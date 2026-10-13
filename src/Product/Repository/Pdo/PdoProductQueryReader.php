<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Repository\Pdo;

use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\Product\DTO\ProductDTO;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Repository\ProductQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Products. It performs no write and holds no lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoProductQueryReader implements ProductQueryReaderInterface
{
    private const string TABLE = 'maa_product_products';

    private RowHydrator $row;

    public function __construct(
        private PDO $pdo,
        ClockInterface $clock,
    ) {
        $this->row = new RowHydrator($clock);
    }

    public function findById(int $productId): ?ProductDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `code`, `status`, `created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::TABLE . '` WHERE `id` = :id LIMIT 1',
        );
        $statement->execute(['id' => $productId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $status = ProductStatusEnum::tryFrom(RowHydrator::string($row, 'status'));
        if ($status === null) {
            throw ProductStorageFailureFactory::invalidStorageValue('status');
        }

        return new ProductDTO(
            id: RowHydrator::positiveInt($row, 'id'),
            code: RowHydrator::string($row, 'code'),
            status: $status,
            createdAt: $this->row->timestamp($row, 'created_at'),
            updatedAt: $this->row->timestamp($row, 'updated_at'),
            deletedAt: $this->row->nullableTimestamp($row, 'deleted_at'),
        );
    }
}
