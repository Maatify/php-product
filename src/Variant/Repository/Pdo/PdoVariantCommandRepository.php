<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Repository\Pdo;

use DateTimeImmutable;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Variant\Enum\VariantStatusEnum;
use Maatify\Product\Variant\Repository\VariantCommandRepositoryInterface;
use Maatify\Product\Variant\ValueObject\CompositionFingerprint;
use PDO;

/**
 * PDO write adapter for Variant persistence on MySQL 8.0.16 or later.
 *
 * Unclassified {@see \PDOException} failures propagate unchanged. The connection
 * must report errors as exceptions and must be the connection of the transaction
 * the caller owns.
 */
final readonly class PdoVariantCommandRepository implements VariantCommandRepositoryInterface
{
    private const string PRODUCT_TABLE = 'maa_product_products';
    private const string VARIANT_TABLE = 'maa_product_variants';

    public function __construct(
        private PDO $pdo,
        private ScopedOrderingManager $orderingManager,
    ) {}

    public function createEmptyComposition(int $productId, DateTimeImmutable $occurredAt): int
    {
        $this->lockProduct($productId);

        $displayOrder = $this->orderingManager->getNextPosition(
            $this->pdo,
            new ScopedOrderingConfig(
                table: self::VARIANT_TABLE,
                scopeColumn: 'product_id',
                idColumn: 'id',
                orderColumn: 'display_order',
                deletedAtColumn: 'deleted_at',
                nullableScope: false,
                updatedAtColumn: 'updated_at',
            ),
            $productId,
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::VARIANT_TABLE . '` '
            . '(`product_id`, `composition_fingerprint`, `sku`, `barcode`, `status`, `display_order`, '
            . '`created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:product_id, :fingerprint, NULL, NULL, :status, :display_order, '
            . ':created_at, :updated_at, NULL)',
        );
        $timestamp = $occurredAt->format('Y-m-d H:i:s');
        $statement->execute([
            'product_id' => $productId,
            'fingerprint' => CompositionFingerprint::forEmptyComposition()->value,
            'status' => VariantStatusEnum::ACTIVE->value,
            'display_order' => $displayOrder,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $id = $this->pdo->lastInsertId();
        if (!ctype_digit($id) || (int) $id < 1) {
            throw ProductStorageFailureFactory::invalidAutoIncrementIdentity('Variant');
        }

        return (int) $id;
    }

    /**
     * Takes the Product aggregate lock for the surrounding transaction.
     *
     * Every mutation that can affect a Product's Variant graph serializes on this
     * row before reading or writing dependent state.
     */
    private function lockProduct(int $productId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT `id` FROM `' . self::PRODUCT_TABLE . '` WHERE `id` = :id FOR UPDATE',
        );
        $statement->execute(['id' => $productId]);
    }
}
