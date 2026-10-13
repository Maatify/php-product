<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Repository\Pdo;

use DateTimeImmutable;
use Exception;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Product\DTO\ProductDTO;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Repository\ProductQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Products. It performs no write and holds no lock.
 */
final readonly class PdoProductQueryReader implements ProductQueryReaderInterface
{
    private const string TABLE = 'maa_product_products';

    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock,
    ) {}

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

        $rowId = $row['id'] ?? null;
        $status = ProductStatusEnum::tryFrom(self::requireString($row, 'status'));
        if ((!is_int($rowId) && !is_string($rowId)) || $status === null) {
            throw ProductStorageFailureFactory::invalidStorageValue($status === null ? 'status' : 'id');
        }

        return new ProductDTO(
            id: (int) $rowId,
            code: self::requireString($row, 'code'),
            status: $status,
            createdAt: $this->timestamp($row, 'created_at'),
            updatedAt: $this->timestamp($row, 'updated_at'),
            deletedAt: $this->nullableTimestamp($row, 'deleted_at'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timestamp(array $row, string $column): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable(self::requireString($row, $column), $this->clock->getTimezone());
        } catch (Exception $exception) {
            throw ProductStorageFailureFactory::invalidStorageValue($column);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableTimestamp(array $row, string $column): ?DateTimeImmutable
    {
        return ($row[$column] ?? null) === null ? null : $this->timestamp($row, $column);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function requireString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw ProductStorageFailureFactory::unexpectedColumnType($column);
        }

        return $value;
    }
}
