<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Repository\Pdo;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\Variant\DTO\VariantCompositionPairDTO;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\Enum\VariantStatusEnum;
use Maatify\Product\Variant\Repository\VariantQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Variants on MySQL 8.0.16 or later.
 *
 * It performs no write and holds no lock. The consumer-visibility predicate lives in SQL so
 * that a list is bounded and filtered by the database.
 */
final readonly class PdoVariantQueryReader implements VariantQueryReaderInterface
{
    private const string COLUMNS = 'v.`id`, v.`product_id`, v.`sku`, v.`barcode`, v.`status`, v.`display_order`, '
        . 'v.`created_at`, v.`updated_at`, v.`deleted_at`';

    private RowHydrator $row;

    public function __construct(
        private PDO $pdo,
        ClockInterface $clock,
    ) {
        $this->row = new RowHydrator($clock);
    }

    public function findById(int $variantId): ?VariantDTO
    {
        $variants = $this->fetch(
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v WHERE v.`id` = :variant_id LIMIT 1',
            ['variant_id' => $variantId],
        );

        return $variants[0] ?? null;
    }

    public function findVisibleById(int $variantId): ?VariantDTO
    {
        $variants = $this->fetch(
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v '
            . 'WHERE v.`id` = :variant_id AND ' . self::visiblePredicate() . ' LIMIT 1',
            ['variant_id' => $variantId],
        );

        return $variants[0] ?? null;
    }

    public function listForManagement(int $productId, VariantListCriteriaDTO $criteria): array
    {
        $where = 'v.`product_id` = :product_id';
        $params = ['product_id' => $productId];
        if ($criteria->status !== null) {
            $where .= ' AND v.`status` = :status';
            $params['status'] = $criteria->status->value;
        }
        $where .= match ($criteria->deletedState) {
            ProductDeletedStateEnum::NON_DELETED => ' AND v.`deleted_at` IS NULL',
            ProductDeletedStateEnum::DELETED_ONLY => ' AND v.`deleted_at` IS NOT NULL',
            ProductDeletedStateEnum::INCLUDE_DELETED => '',
        };

        return $this->fetch(
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v WHERE ' . $where
            . ' ORDER BY v.`display_order` ASC, v.`id` ASC LIMIT ' . $criteria->maxResults,
            $params,
        );
    }

    public function listVisible(int $productId, VariantVisibleListCriteriaDTO $criteria): array
    {
        return $this->fetch(
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v '
            . 'WHERE v.`product_id` = :product_id AND ' . self::visiblePredicate()
            . ' ORDER BY v.`display_order` ASC, v.`id` ASC LIMIT ' . $criteria->maxResults,
            ['product_id' => $productId],
        );
    }

    /**
     * Consumer visibility of the Variant aliased `v`: its Product and the Variant itself are
     * active and not deleted, every selected Option and Value is active, not deleted, and
     * labelled, and the composition covers exactly the Product's active Options. A pair per
     * Option is unique, so equal counts over active Options mean exact coverage.
     */
    private static function visiblePredicate(): string
    {
        $labelled = static fn(string $table, string $key, string $alias): string => 'EXISTS (SELECT 1 FROM `' . $table . '` c '
            . 'WHERE c.`' . $key . '` = ' . $alias . '.`id` AND c.`deleted_at` IS NULL '
            . "AND c.`name` REGEXP '[^[:space:]]')";

        return 'v.`deleted_at` IS NULL AND v.`status` = \'active\' '
            . 'AND EXISTS (SELECT 1 FROM `maa_product_products` p WHERE p.`id` = v.`product_id` '
            . 'AND p.`deleted_at` IS NULL AND p.`status` = \'active\') '
            . 'AND NOT EXISTS (SELECT 1 FROM `maa_product_variant_option_values` cv '
            . 'JOIN `maa_product_options` o ON o.`id` = cv.`option_id` '
            . 'JOIN `maa_product_option_values` ov ON ov.`id` = cv.`option_value_id` '
            . 'WHERE cv.`variant_id` = v.`id` AND ('
            . 'o.`deleted_at` IS NOT NULL OR o.`status` <> \'active\' '
            . 'OR ov.`deleted_at` IS NOT NULL OR ov.`status` <> \'active\' '
            . 'OR NOT ' . $labelled('maa_product_option_contents', 'option_id', 'o') . ' '
            . 'OR NOT ' . $labelled('maa_product_option_value_contents', 'option_value_id', 'ov') . ')) '
            . 'AND (SELECT COUNT(*) FROM `maa_product_variant_option_values` cp WHERE cp.`variant_id` = v.`id`) = '
            . '(SELECT COUNT(*) FROM `maa_product_options` ao WHERE ao.`product_id` = v.`product_id` '
            . 'AND ao.`deleted_at` IS NULL AND ao.`status` = \'active\')';
    }

    /**
     * @param array<string, int|string> $params
     *
     * @return list<VariantDTO>
     */
    private function fetch(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        $compositions = $this->compositions(array_map(
            static fn(array $row): int => RowHydrator::positiveInt($row, 'id'),
            $rows,
        ));

        $variants = [];
        foreach ($rows as $row) {
            $id = RowHydrator::positiveInt($row, 'id');
            $status = VariantStatusEnum::tryFrom(RowHydrator::string($row, 'status'));
            if ($status === null) {
                throw ProductStorageFailureFactory::invalidStorageValue('status');
            }
            $displayOrder = $row['display_order'] ?? null;
            if (!is_int($displayOrder) && !is_string($displayOrder)) {
                throw ProductStorageFailureFactory::unexpectedColumnType('display_order');
            }

            $variants[] = new VariantDTO(
                id: $id,
                productId: RowHydrator::positiveInt($row, 'product_id'),
                sku: RowHydrator::nullableString($row, 'sku'),
                barcode: RowHydrator::nullableString($row, 'barcode'),
                status: $status,
                displayOrder: (int) $displayOrder,
                composition: $compositions[$id] ?? [],
                createdAt: $this->row->timestamp($row, 'created_at'),
                updatedAt: $this->row->timestamp($row, 'updated_at'),
                deletedAt: $this->row->nullableTimestamp($row, 'deleted_at'),
            );
        }

        return $variants;
    }

    /**
     * @param non-empty-list<positive-int> $variantIds
     *
     * @return array<int, list<VariantCompositionPairDTO>> Pairs per Variant, ordered by numeric Option identity.
     */
    private function compositions(array $variantIds): array
    {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $statement = $this->pdo->prepare(
            'SELECT `variant_id`, `option_id`, `option_value_id` FROM `maa_product_variant_option_values` '
            . 'WHERE `variant_id` IN (' . $placeholders . ') ORDER BY `variant_id` ASC, `option_id` ASC',
        );
        $statement->execute($variantIds);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $pairs = [];
        foreach ($rows as $row) {
            $pairs[RowHydrator::positiveInt($row, 'variant_id')][] = new VariantCompositionPairDTO(
                RowHydrator::positiveInt($row, 'option_id'),
                RowHydrator::positiveInt($row, 'option_value_id'),
            );
        }

        return $pairs;
    }
}
