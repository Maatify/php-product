<?php

declare(strict_types=1);

namespace Maatify\Product\Product\Repository\Pdo;

use DateTimeImmutable;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\Option\DTO\OptionStateDTO;
use Maatify\Product\OptionValue\DTO\OptionValueStateDTO;
use Maatify\Product\Product\DTO\ProductConfigurationSnapshotDTO;
use Maatify\Product\Product\Repository\ProductConfigurationRepositoryInterface;
use Maatify\Product\Variant\DTO\VariantStateDTO;
use Maatify\Product\Variant\ValueObject\CompositionFingerprint;
use Maatify\Product\Variant\ValueObject\OptionValueSelection;
use PDO;

/**
 * PDO adapter over the configuration graph of one Product on MySQL 8.0.16 or later.
 *
 * The snapshot is read with locking reads only, so it reflects the latest committed state
 * even when the Host transaction established an earlier consistent-read snapshot. Lock order:
 * Product row, Options, Option Values, Variants, each by ascending identity; composition pairs
 * and Content labels are read with shared locks after them.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoProductConfigurationRepository implements ProductConfigurationRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private ScopedOrderingManager $orderingManager,
    ) {}

    public function lockSnapshot(int $productId): ?ProductConfigurationSnapshotDTO
    {
        $product = $this->pdo->prepare(
            'SELECT `id`, `status`, (`deleted_at` IS NOT NULL) AS `deleted` FROM `maa_product_products` WHERE `id` = :id FOR UPDATE',
        );
        $product->execute(['id' => $productId]);
        /** @var array<string, mixed>|false $productRow */
        $productRow = $product->fetch(PDO::FETCH_ASSOC);
        if ($productRow === false) {
            return null;
        }

        $options = $this->pdo->prepare(
            'SELECT `id`, `product_id`, `status`, (`deleted_at` IS NOT NULL) AS `deleted` FROM `maa_product_options` WHERE '
            . '`product_id` = :product_id ORDER BY `id` ASC FOR UPDATE',
        );
        $options->execute(['product_id' => $productId]);
        /** @var list<array<string, mixed>> $optionRows */
        $optionRows = $options->fetchAll(PDO::FETCH_ASSOC);

        $values = $this->pdo->prepare(
            'SELECT ov.`id`, ov.`option_id`, ov.`status`, (ov.`deleted_at` IS NOT NULL) AS `deleted` '
            . 'FROM `maa_product_option_values` ov JOIN `maa_product_options` o ON o.`id` = ov.`option_id` WHERE '
            . 'o.`product_id` = :product_id ORDER BY ov.`id` ASC FOR UPDATE',
        );
        $values->execute(['product_id' => $productId]);
        /** @var list<array<string, mixed>> $valueRows */
        $valueRows = $values->fetchAll(PDO::FETCH_ASSOC);

        $variants = $this->pdo->prepare(
            'SELECT `id`, `product_id`, `composition_fingerprint`, `status`, (`deleted_at` IS NOT NULL) AS `deleted` '
            . 'FROM `maa_product_variants` WHERE `product_id` = :product_id ORDER BY `id` ASC FOR UPDATE',
        );
        $variants->execute(['product_id' => $productId]);
        /** @var list<array<string, mixed>> $variantRows */
        $variantRows = $variants->fetchAll(PDO::FETCH_ASSOC);

        $pairs = $this->pdo->prepare(
            'SELECT `variant_id`, `option_id`, `option_value_id` FROM `maa_product_variant_option_values` WHERE '
            . '`product_id` = :product_id ORDER BY `variant_id` ASC, `option_id` ASC FOR SHARE',
        );
        $pairs->execute(['product_id' => $productId]);
        /** @var list<array<string, mixed>> $pairRows */
        $pairRows = $pairs->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<int, list<OptionValueSelection>> $compositions */
        $compositions = [];
        foreach ($pairRows as $row) {
            $compositions[RowHydrator::positiveInt($row, 'variant_id')][] = new OptionValueSelection(
                RowHydrator::positiveInt($row, 'option_id'),
                RowHydrator::positiveInt($row, 'option_value_id'),
            );
        }

        $labelledOptions = $this->labelledOptions($productId);
        $labelledValues = $this->labelledOptionValues($productId);

        $name = $this->pdo->prepare(
            'SELECT 1 FROM `maa_product_product_contents` WHERE `product_id` = :product_id AND `deleted_at` IS NULL '
            . 'AND `name` REGEXP \'[^[:space:]]\' LIMIT 1 FOR SHARE',
        );
        $name->execute(['product_id' => $productId]);

        $optionStates = [];
        foreach ($optionRows as $row) {
            $id = RowHydrator::positiveInt($row, 'id');
            $optionStates[$id] = new OptionStateDTO(
                id: $id,
                productId: RowHydrator::positiveInt($row, 'product_id'),
                active: RowHydrator::isActive($row),
                deleted: RowHydrator::flag($row, 'deleted'),
                labelled: isset($labelledOptions[$id]),
            );
        }
        $valueStates = [];
        foreach ($valueRows as $row) {
            $id = RowHydrator::positiveInt($row, 'id');
            $valueStates[$id] = new OptionValueStateDTO(
                id: $id,
                optionId: RowHydrator::positiveInt($row, 'option_id'),
                active: RowHydrator::isActive($row),
                deleted: RowHydrator::flag($row, 'deleted'),
                labelled: isset($labelledValues[$id]),
            );
        }
        $variantStates = [];
        foreach ($variantRows as $row) {
            $id = RowHydrator::positiveInt($row, 'id');
            $variantStates[$id] = new VariantStateDTO(
                id: $id,
                productId: RowHydrator::positiveInt($row, 'product_id'),
                fingerprint: RowHydrator::string($row, 'composition_fingerprint'),
                active: RowHydrator::isActive($row),
                deleted: RowHydrator::flag($row, 'deleted'),
                composition: $compositions[$id] ?? [],
            );
        }

        return new ProductConfigurationSnapshotDTO(
            productId: RowHydrator::positiveInt($productRow, 'id'),
            productActive: RowHydrator::isActive($productRow),
            productDeleted: RowHydrator::flag($productRow, 'deleted'),
            hasName: $name->fetchColumn() !== false,
            options: $optionStates,
            optionValues: $valueStates,
            variants: $variantStates,
        );
    }

    public function findVariantProductId(int $variantId): ?int
    {
        $statement = $this->pdo->prepare('SELECT `product_id` FROM `maa_product_variants` WHERE `id` = :id LIMIT 1');
        $statement->execute(['id' => $variantId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : RowHydrator::positiveInt($row, 'product_id');
    }

    public function updateOptionStatus(array $optionIds, bool $active, DateTimeImmutable $occurredAt): void
    {
        $this->updateStatusOf('maa_product_options', $optionIds, $active, $occurredAt);
    }

    public function updateOptionValueStatus(array $optionValueIds, bool $active, DateTimeImmutable $occurredAt): void
    {
        $this->updateStatusOf('maa_product_option_values', $optionValueIds, $active, $occurredAt);
    }

    public function updateVariantStatus(array $variantIds, bool $active, DateTimeImmutable $occurredAt): void
    {
        $this->updateStatusOf('maa_product_variants', $variantIds, $active, $occurredAt);
    }

    public function restoreVariantAsActive(int $variantId, int $productId, DateTimeImmutable $occurredAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE `maa_product_variants` SET `deleted_at` = NULL, `status` = \'active\', `display_order` = :display_order, '
            . '`updated_at` = :updated_at WHERE `id` = :id AND `deleted_at` IS NOT NULL',
        );
        $statement->execute([
            'display_order' => $this->orderingManager->getNextPosition($this->pdo, self::variantOrderingConfig(), $productId),
            'updated_at' => $occurredAt->format('Y-m-d H:i:s'),
            'id' => $variantId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function createActiveVariant(
        int $productId,
        CompositionFingerprint $fingerprint,
        array $composition,
        DateTimeImmutable $occurredAt,
    ): int {
        $displayOrder = $this->orderingManager->getNextPosition(
            $this->pdo,
            self::variantOrderingConfig(),
            $productId,
        );
        $timestamp = $occurredAt->format('Y-m-d H:i:s');
        $insert = $this->pdo->prepare(
            'INSERT INTO `maa_product_variants` (`product_id`, `composition_fingerprint`, `sku`, `barcode`, `status`, '
            . '`display_order`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (:product_id, :fingerprint, NULL, NULL, \'active\', :display_order, :created_at, :updated_at, NULL)',
        );
        $insert->execute([
            'product_id' => $productId,
            'fingerprint' => $fingerprint->value,
            'display_order' => $displayOrder,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $id = $this->pdo->lastInsertId();
        if (!ctype_digit($id) || (int) $id < 1) {
            throw ProductStorageFailureFactory::invalidAutoIncrementIdentity('Variant');
        }
        $variantId = (int) $id;

        if ($composition !== []) {
            $pair = $this->pdo->prepare(
                'INSERT INTO `maa_product_variant_option_values` (`product_id`, `variant_id`, `option_id`, `option_value_id`) '
                . 'VALUES (:product_id, :variant_id, :option_id, :option_value_id)',
            );
            foreach ($composition as $selection) {
                $pair->execute([
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'option_id' => $selection->optionId,
                    'option_value_id' => $selection->optionValueId,
                ]);
            }
        }

        return $variantId;
    }

    /**
     * Variants are ordered within their Product; soft-deleted Variants are outside the live order.
     */
    private static function variantOrderingConfig(): ScopedOrderingConfig
    {
        return new ScopedOrderingConfig(
            table: 'maa_product_variants',
            scopeColumn: 'product_id',
            idColumn: 'id',
            orderColumn: 'display_order',
            deletedAtColumn: 'deleted_at',
            nullableScope: false,
            updatedAtColumn: 'updated_at',
        );
    }

    /**
     * @param list<int> $ids
     */
    private function updateStatusOf(string $table, array $ids, bool $active, DateTimeImmutable $occurredAt): void
    {
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'UPDATE `' . $table . '` SET `status` = ?, `updated_at` = ? WHERE `deleted_at` IS NULL AND `id` IN (' . $placeholders . ')',
        );
        $statement->execute([$active ? 'active' : 'inactive', $occurredAt->format('Y-m-d H:i:s'), ...$ids]);
    }

    /**
     * Identities of the Product's Options that have a non-deleted, non-empty label.
     *
     * @return array<int, true>
     */
    private function labelledOptions(int $productId): array
    {
        return $this->labelledOwners(
            'SELECT DISTINCT c.`option_id` AS `owner` FROM `maa_product_option_contents` c '
            . 'JOIN `maa_product_options` o ON o.`id` = c.`option_id` WHERE '
            . 'o.`product_id` = :product_id AND c.`deleted_at` IS NULL AND c.`name` REGEXP \'[^[:space:]]\' FOR SHARE',
            $productId,
        );
    }

    /**
     * Identities of the Product's Option Values that have a non-deleted, non-empty label.
     *
     * @return array<int, true>
     */
    private function labelledOptionValues(int $productId): array
    {
        return $this->labelledOwners(
            'SELECT DISTINCT c.`option_value_id` AS `owner` FROM `maa_product_option_value_contents` c '
            . 'JOIN `maa_product_option_values` ov ON ov.`id` = c.`option_value_id` '
            . 'JOIN `maa_product_options` o ON o.`id` = ov.`option_id` WHERE '
            . 'o.`product_id` = :product_id AND c.`deleted_at` IS NULL AND c.`name` REGEXP \'[^[:space:]]\' FOR SHARE',
            $productId,
        );
    }

    /**
     * @return array<int, true>
     */
    private function labelledOwners(string $sql, int $productId): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['product_id' => $productId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $owners = [];
        foreach ($rows as $row) {
            $owners[RowHydrator::positiveInt($row, 'owner')] = true;
        }

        return $owners;
    }
}
