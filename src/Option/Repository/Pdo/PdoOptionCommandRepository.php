<?php

declare(strict_types=1);

namespace Maatify\Product\Option\Repository\Pdo;

use DateTimeImmutable;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\Option\Command\CreateOptionCommand;
use Maatify\Product\Option\DTO\OptionStateDTO;
use Maatify\Product\Option\Exception\OptionCodeAlreadyExistsException;
use Maatify\Product\Option\Repository\OptionCommandRepositoryInterface;
use PDO;
use PDOException;

/**
 * PDO write adapter for Option persistence on MySQL 8.0.16 or later.
 *
 * Only a duplicate-key error (MySQL driver code 1062) on insert is converted, to
 * {@see OptionCodeAlreadyExistsException}; the `(product_id, code)` key is the only unique key
 * an insert can violate besides the generated keys. Every other {@see PDOException} propagates.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoOptionCommandRepository implements OptionCommandRepositoryInterface
{
    private const string TABLE = 'maa_product_options';
    private const int MYSQL_DUPLICATE_KEY = 1062;

    public function __construct(
        private PDO $pdo,
        private ScopedOrderingManager $orderingManager,
    ) {}

    public function create(CreateOptionCommand $command, DateTimeImmutable $occurredAt): int
    {
        $displayOrder = $this->orderingManager->getNextPosition(
            $this->pdo,
            new ScopedOrderingConfig(
                table: self::TABLE,
                scopeColumn: 'product_id',
                idColumn: 'id',
                orderColumn: 'display_order',
                deletedAtColumn: 'deleted_at',
                nullableScope: false,
                updatedAtColumn: 'updated_at',
            ),
            $command->productId,
        );
        $timestamp = $occurredAt->format('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::TABLE . '` (`product_id`, `code`, `status`, `display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:product_id, :code, \'inactive\', :display_order, :created_at, :updated_at, NULL)',
        );
        try {
            $statement->execute([
                'product_id' => $command->productId,
                'code' => $command->code,
                'display_order' => $displayOrder,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } catch (PDOException $exception) {
            $driverCode = $exception->errorInfo[1] ?? null;
            if ((is_int($driverCode) || is_string($driverCode)) && (int) $driverCode === self::MYSQL_DUPLICATE_KEY) {
                throw OptionCodeAlreadyExistsException::withCode($command->productId, $command->code, $exception);
            }

            throw $exception;
        }

        $id = $this->pdo->lastInsertId();
        if (!ctype_digit($id) || (int) $id < 1) {
            throw ProductStorageFailureFactory::invalidAutoIncrementIdentity('Option');
        }

        return (int) $id;
    }

    public function lockForUpdate(int $optionId): ?OptionStateDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT o.`id`, o.`product_id`, o.`status`, (o.`deleted_at` IS NOT NULL) AS `deleted`, '
            . 'EXISTS (SELECT 1 FROM `maa_product_option_contents` c WHERE c.`option_id` = o.`id` AND c.`deleted_at` IS NULL '
            . 'AND c.`name` REGEXP \'[^[:space:]]\') AS `labelled` '
            . 'FROM `' . self::TABLE . '` o WHERE o.`id` = :id FOR UPDATE',
        );
        $statement->execute(['id' => $optionId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new OptionStateDTO(
            id: RowHydrator::positiveInt($row, 'id'),
            productId: RowHydrator::positiveInt($row, 'product_id'),
            active: RowHydrator::isActive($row),
            deleted: RowHydrator::flag($row, 'deleted'),
            labelled: RowHydrator::flag($row, 'labelled'),
        );
    }

    public function findProductId(int $optionId): ?int
    {
        $statement = $this->pdo->prepare('SELECT `product_id` FROM `' . self::TABLE . '` WHERE `id` = :id LIMIT 1');
        $statement->execute(['id' => $optionId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : RowHydrator::positiveInt($row, 'product_id');
    }

    public function softDelete(int $optionId, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::TABLE . '` SET `deleted_at` = :deleted_at, `updated_at` = :updated_at WHERE `id` = :id AND `deleted_at` IS NULL',
        );
        $timestamp = $occurredAt->format('Y-m-d H:i:s');
        $statement->execute(['deleted_at' => $timestamp, 'updated_at' => $timestamp, 'id' => $optionId]);

        return $statement->rowCount() > 0;
    }

    public function restore(int $optionId, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `' . self::TABLE . '` SET `deleted_at` = NULL, `updated_at` = :updated_at WHERE `id` = :id AND `deleted_at` IS NOT NULL',
        );
        $statement->execute(['updated_at' => $occurredAt->format('Y-m-d H:i:s'), 'id' => $optionId]);

        return $statement->rowCount() > 0;
    }
}
