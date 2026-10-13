<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Repository\Pdo;

use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\ManagementListQuery;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\Option\DTO\OptionStateDTO;
use Maatify\Product\OptionValue\DTO\OptionValueStateDTO;
use Maatify\Product\Variant\DTO\VariantCompositionPairDTO;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantStateDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\Enum\VariantStatusEnum;
use Maatify\Product\Variant\Repository\VariantQueryReaderInterface;
use Maatify\Product\Variant\ValueObject\OptionValueSelection;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Variants on MySQL 8.0.16 or later.
 *
 * It performs no write and holds no lock. The consumer-visibility predicate lives in SQL so
 * that a list is bounded and filtered by the database.
 *
 * @internal Not part of the Public Runtime API.
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
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v WHERE '
            . 'v.`id` = :variant_id AND ' . self::visiblePredicate('v') . ' LIMIT 1',
            ['variant_id' => $variantId],
        );

        return $variants[0] ?? null;
    }

    public function listForManagement(int $productId, VariantListCriteriaDTO $criteria): array
    {
        [$where, $params] = self::managementFilter($productId, $criteria);

        return $this->hydrate(ManagementListQuery::fetchBounded(
            $this->pdo,
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v WHERE ' . $where
            . ' ORDER BY v.`display_order` ASC, v.`id` ASC LIMIT :list_limit',
            $params,
            $criteria->maxResults,
        ));
    }

    public function paginateForManagement(int $productId, VariantListCriteriaDTO $criteria, PageRequest $request): PageResult
    {
        [$where, $params] = self::managementFilter($productId, $criteria);
        $count = 'SELECT COUNT(*) FROM `maa_product_variants` v WHERE ' . $where;
        $page = ManagementListQuery::paginate(
            $this->pdo,
            $count,
            $params,
            $count,
            $params,
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v WHERE ' . $where,
            $request,
            ManagementListQuery::config(
                ['display_order' => 'v.display_order', 'id' => 'v.id', 'created_at' => 'v.created_at', 'updated_at' => 'v.updated_at'],
                'display_order',
            ),
            static fn(array $row): array => $row,
        );

        // The rows are hydrated together so the composition pairs of the page load in one query.
        return new PageResult(
            $this->hydrate($page->data),
            $page->page,
            $page->perPage,
            $page->total,
            $page->filtered,
            $page->totalPages,
            $page->hasNext,
            $page->hasPrevious,
            $page->sortBy,
            $page->sortDirection,
        );
    }

    public function listVisible(int $productId, VariantVisibleListCriteriaDTO $criteria): array
    {
        return $this->hydrate(ManagementListQuery::fetchBounded(
            $this->pdo,
            'SELECT ' . self::COLUMNS . ' FROM `maa_product_variants` v WHERE '
            . 'v.`product_id` = :product_id AND ' . self::visiblePredicate('v')
            . ' ORDER BY v.`display_order` ASC, v.`id` ASC LIMIT :list_limit',
            ['product_id' => $productId],
            $criteria->maxResults,
        ));
    }

    /**
     * The management filter shared by the list, the counts, and the page.
     *
     * @return array{string, array<string, string|int>}
     */
    private static function managementFilter(int $productId, VariantListCriteriaDTO $criteria): array
    {
        $where = 'v.`product_id` = :product_id';
        $params = ['product_id' => $productId];
        if ($criteria->status !== null) {
            $where .= ' AND v.`status` = :status';
            $params['status'] = $criteria->status->value;
        }

        return [$where . ManagementListQuery::deletedCondition($criteria->deletedState, 'v.'), $params];
    }

    public function isProductVisible(int $productId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM `maa_product_products` WHERE `id` = :id AND `deleted_at` IS NULL AND `status` = \'active\' LIMIT 1',
        );
        $statement->execute(['id' => $productId]);

        return $statement->fetchColumn() !== false;
    }

    public function listOptionStates(int $productId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT o.`id`, o.`product_id`, o.`status`, (o.`deleted_at` IS NOT NULL) AS `deleted`, '
            . self::labelled('maa_product_option_contents', 'option_id', 'o') . ' AS `labelled` '
            . 'FROM `maa_product_options` o WHERE o.`product_id` = :product_id ORDER BY o.`id` ASC',
        );
        $statement->execute(['product_id' => $productId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $options = [];
        foreach ($rows as $row) {
            $options[] = new OptionStateDTO(
                id: RowHydrator::positiveInt($row, 'id'),
                productId: RowHydrator::positiveInt($row, 'product_id'),
                active: RowHydrator::isActive($row),
                deleted: RowHydrator::flag($row, 'deleted'),
                labelled: RowHydrator::flag($row, 'labelled'),
            );
        }

        return $options;
    }

    public function listOptionValueStates(int $productId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ov.`id`, ov.`option_id`, ov.`status`, (ov.`deleted_at` IS NOT NULL) AS `deleted`, '
            . self::labelled('maa_product_option_value_contents', 'option_value_id', 'ov') . ' AS `labelled` '
            . 'FROM `maa_product_option_values` ov JOIN `maa_product_options` o ON o.`id` = ov.`option_id` WHERE '
            . 'o.`product_id` = :product_id ORDER BY ov.`id` ASC',
        );
        $statement->execute(['product_id' => $productId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $values = [];
        foreach ($rows as $row) {
            $values[] = new OptionValueStateDTO(
                id: RowHydrator::positiveInt($row, 'id'),
                optionId: RowHydrator::positiveInt($row, 'option_id'),
                active: RowHydrator::isActive($row),
                deleted: RowHydrator::flag($row, 'deleted'),
                labelled: RowHydrator::flag($row, 'labelled'),
            );
        }

        return $values;
    }

    public function findActiveByFingerprint(int $productId, string $fingerprint): ?VariantStateDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `product_id`, `composition_fingerprint`, `status`, (`deleted_at` IS NOT NULL) AS `deleted` '
            . 'FROM `maa_product_variants` WHERE `product_id` = :product_id AND `composition_fingerprint` = :fingerprint '
            . 'AND `deleted_at` IS NULL AND `status` = \'active\' LIMIT 1',
        );
        $statement->execute(['product_id' => $productId, 'fingerprint' => $fingerprint]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $id = RowHydrator::positiveInt($row, 'id');
        $pairs = $this->compositions([$id])[$id] ?? [];

        return new VariantStateDTO(
            id: $id,
            productId: RowHydrator::positiveInt($row, 'product_id'),
            fingerprint: RowHydrator::string($row, 'composition_fingerprint'),
            active: RowHydrator::isActive($row),
            deleted: RowHydrator::flag($row, 'deleted'),
            composition: array_map(
                static fn(VariantCompositionPairDTO $pair): OptionValueSelection => new OptionValueSelection($pair->optionId, $pair->optionValueId),
                $pairs,
            ),
        );
    }

    /**
     * Consumer visibility of the Variant aliased `$v`: its Product and the Variant itself are
     * active and not deleted, its own composition is valid, and the Product's configuration is
     * valid as a whole: no active Value of an active Option is left unrepresented by an active,
     * valid Variant. The product-wide rule fails closed, so a broken configuration hides every
     * Variant of the Product from the single get and from the list alike.
     */
    private static function visiblePredicate(string $v): string
    {
        return $v . '.`deleted_at` IS NULL AND ' . $v . '.`status` = \'active\' '
            . 'AND EXISTS (SELECT 1 FROM `maa_product_products` p WHERE p.`id` = ' . $v . '.`product_id` '
            . 'AND p.`deleted_at` IS NULL AND p.`status` = \'active\') '
            . 'AND ' . self::validCompositionPredicate($v, 'a')
            . ' AND NOT EXISTS (SELECT 1 FROM `maa_product_option_values` uv '
            . 'JOIN `maa_product_options` uo ON uo.`id` = uv.`option_id` WHERE '
            . 'uo.`product_id` = ' . $v . '.`product_id` AND uo.`deleted_at` IS NULL AND uo.`status` = \'active\' '
            . 'AND uv.`deleted_at` IS NULL AND uv.`status` = \'active\' '
            . 'AND NOT EXISTS (SELECT 1 FROM `maa_product_variants` rv '
            . 'JOIN `maa_product_variant_option_values` rp ON rp.`variant_id` = rv.`id` WHERE '
            . 'rp.`option_value_id` = uv.`id` AND rv.`product_id` = ' . $v . '.`product_id` '
            . 'AND rv.`deleted_at` IS NULL AND rv.`status` = \'active\' '
            . 'AND ' . self::validCompositionPredicate('rv', 'b') . '))';
    }

    /**
     * Structural validity of the composition of the Variant aliased `$v`: every selected Option
     * and Value is active, not deleted, and labelled, and the composition covers exactly the
     * Product's active Options. A pair per Option is unique, so equal counts over active
     * Options mean exact coverage. `$s` makes the inner aliases unique per nesting level.
     */
    private static function validCompositionPredicate(string $v, string $s): string
    {
        return 'NOT EXISTS (SELECT 1 FROM `maa_product_variant_option_values` cv' . $s . ' '
            . 'JOIN `maa_product_options` o' . $s . ' ON o' . $s . '.`id` = cv' . $s . '.`option_id` '
            . 'JOIN `maa_product_option_values` ov' . $s . ' ON ov' . $s . '.`id` = cv' . $s . '.`option_value_id` WHERE '
            . 'cv' . $s . '.`variant_id` = ' . $v . '.`id` AND ('
            . 'o' . $s . '.`deleted_at` IS NOT NULL OR o' . $s . '.`status` <> \'active\' '
            . 'OR ov' . $s . '.`deleted_at` IS NOT NULL OR ov' . $s . '.`status` <> \'active\' '
            . 'OR NOT ' . self::labelled('maa_product_option_contents', 'option_id', 'o' . $s) . ' '
            . 'OR NOT ' . self::labelled('maa_product_option_value_contents', 'option_value_id', 'ov' . $s) . ')) '
            . 'AND (SELECT COUNT(*) FROM `maa_product_variant_option_values` cp' . $s . ' WHERE cp' . $s . '.`variant_id` = ' . $v . '.`id`) = '
            . '(SELECT COUNT(*) FROM `maa_product_options` ao' . $s . ' WHERE ao' . $s . '.`product_id` = ' . $v . '.`product_id` '
            . 'AND ao' . $s . '.`deleted_at` IS NULL AND ao' . $s . '.`status` = \'active\')';
    }

    /**
     * `EXISTS` predicate: the owner aliased `$alias` has a non-deleted Content record with a non-empty label.
     */
    private static function labelled(string $table, string $key, string $alias): string
    {
        return 'EXISTS (SELECT 1 FROM `' . $table . '` c_' . $alias . ' WHERE c_' . $alias . '.`' . $key . '` = ' . $alias . '.`id` '
            . 'AND c_' . $alias . '.`deleted_at` IS NULL AND c_' . $alias . '.`name` REGEXP \'[^[:space:]]\')';
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

        return $this->hydrate($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<VariantDTO>
     */
    private function hydrate(array $rows): array
    {
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
            'SELECT `variant_id`, `option_id`, `option_value_id` FROM `maa_product_variant_option_values` WHERE '
            . '`variant_id` IN (' . $placeholders . ') ORDER BY `variant_id` ASC, `option_id` ASC',
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
