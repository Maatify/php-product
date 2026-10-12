<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Repository\Pdo;

use Maatify\Product\Common\Exception\ProductPersistenceException;
use Maatify\Product\Product\DTO\ProductDTO;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Repository\ProductQueryReaderInterface;
use PDO;

/**
 * PDO read adapter for stored Products. It performs no write and holds no lock.
 */
final readonly class PdoProductQueryReader implements ProductQueryReaderInterface
{
    private const string TABLE = 'maa_product_products';

    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?ProductDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `code`, `status`, `created_at`, `updated_at`, `deleted_at` '
            . 'FROM `' . self::TABLE . '` WHERE `id` = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $rowId = $row['id'] ?? null;
        $status = ProductStatusEnum::tryFrom(self::requireString($row, 'status'));
        if ((!is_int($rowId) && !is_string($rowId)) || $status === null) {
            throw ProductPersistenceException::invalidStorageValue($status === null ? 'status' : 'id');
        }

        $deletedAt = $row['deleted_at'] ?? null;
        if ($deletedAt !== null && !is_string($deletedAt)) {
            throw ProductPersistenceException::unexpectedColumnType('deleted_at');
        }

        return new ProductDTO(
            id: (int) $rowId,
            code: self::requireString($row, 'code'),
            status: $status,
            createdAt: self::requireString($row, 'created_at'),
            updatedAt: self::requireString($row, 'updated_at'),
            deletedAt: $deletedAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function requireString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw ProductPersistenceException::unexpectedColumnType($column);
        }

        return $value;
    }
}
