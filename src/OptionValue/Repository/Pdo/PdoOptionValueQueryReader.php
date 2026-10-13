<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Repository\Pdo;

use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\ManagementListQuery;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\OptionValue\DTO\OptionValueDTO;
use Maatify\Product\OptionValue\DTO\OptionValueListCriteriaDTO;
use Maatify\Product\OptionValue\Enum\OptionValueStatusEnum;
use Maatify\Product\OptionValue\Repository\OptionValueQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Option Values. It performs no write and holds no lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoOptionValueQueryReader implements OptionValueQueryReaderInterface
{
    private const string TABLE = 'maa_product_option_values';
    private const string COLUMNS = '`id`, `option_id`, `code`, `status`, `display_order`, `created_at`, `updated_at`, `deleted_at`';

    private RowHydrator $row;

    public function __construct(
        private PDO $pdo,
        ClockInterface $clock,
    ) {
        $this->row = new RowHydrator($clock);
    }

    public function findById(int $optionValueId): ?OptionValueDTO
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM `' . self::TABLE . '` WHERE `id` = :id LIMIT 1');
        $statement->execute(['id' => $optionValueId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function listForManagement(int $optionId, OptionValueListCriteriaDTO $criteria): array
    {
        [$where, $params] = self::filter($optionId, $criteria);

        return array_map($this->hydrate(...), ManagementListQuery::fetchBounded(
            $this->pdo,
            'SELECT ' . self::COLUMNS . ' FROM `' . self::TABLE . '` WHERE ' . $where . ' ORDER BY `display_order` ASC, `id` ASC LIMIT :list_limit',
            $params,
            $criteria->maxResults,
        ));
    }

    public function paginateForManagement(int $optionId, OptionValueListCriteriaDTO $criteria, PageRequest $request): PageResult
    {
        [$where, $params] = self::filter($optionId, $criteria);
        $from = ' FROM `' . self::TABLE . '` WHERE ' . $where;

        return ManagementListQuery::paginate(
            $this->pdo,
            'SELECT COUNT(*)' . $from,
            $params,
            'SELECT COUNT(*)' . $from,
            $params,
            'SELECT ' . self::COLUMNS . $from,
            $request,
            ManagementListQuery::config(
                ['display_order' => 'display_order', 'id' => 'id', 'created_at' => 'created_at', 'updated_at' => 'updated_at'],
                'display_order',
            ),
            $this->hydrate(...),
        );
    }

    /**
     * The owner, status, and deleted-state filter shared by the list, the counts, and the page.
     *
     * @return array{string, array<string, string|int>}
     */
    private static function filter(int $optionId, OptionValueListCriteriaDTO $criteria): array
    {
        $where = '`option_id` = :owner_id';
        $params = ['owner_id' => $optionId];
        if ($criteria->status !== null) {
            $where .= ' AND `status` = :status';
            $params['status'] = $criteria->status->value;
        }

        return [$where . ManagementListQuery::deletedCondition($criteria->deletedState), $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OptionValueDTO
    {
        $status = OptionValueStatusEnum::tryFrom(RowHydrator::string($row, 'status'));
        if ($status === null) {
            throw ProductStorageFailureFactory::invalidStorageValue('status');
        }

        return new OptionValueDTO(
            id: RowHydrator::positiveInt($row, 'id'),
            optionId: RowHydrator::positiveInt($row, 'option_id'),
            code: RowHydrator::string($row, 'code'),
            status: $status,
            displayOrder: RowHydrator::positiveInt($row, 'display_order'),
            createdAt: $this->row->timestamp($row, 'created_at'),
            updatedAt: $this->row->timestamp($row, 'updated_at'),
            deletedAt: $this->row->nullableTimestamp($row, 'deleted_at'),
        );
    }
}
