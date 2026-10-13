<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Repository\Pdo;

use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\OptionValue\DTO\OptionValueDTO;
use Maatify\Product\OptionValue\Enum\OptionValueStatusEnum;
use Maatify\Product\OptionValue\Repository\OptionValueQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Option Values.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoOptionValueQueryReader implements OptionValueQueryReaderInterface
{
    private RowHydrator $row;

    public function __construct(
        private PDO $pdo,
        ClockInterface $clock,
    ) {
        $this->row = new RowHydrator($clock);
    }

    public function findById(int $optionValueId): ?OptionValueDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `option_id`, `code`, `status`, `display_order`, `created_at`, `updated_at`, `deleted_at` '
            . 'FROM `maa_product_option_values` WHERE `id` = :id LIMIT 1',
        );
        $statement->execute(['id' => $optionValueId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
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
