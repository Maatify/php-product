<?php

declare(strict_types=1);

namespace Maatify\Product\Option\Repository\Pdo;

use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Common\Pdo\RowHydrator;
use Maatify\Product\Option\DTO\OptionDTO;
use Maatify\Product\Option\Enum\OptionStatusEnum;
use Maatify\Product\Option\Repository\OptionQueryReaderInterface;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;

/**
 * PDO read adapter for stored Options.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class PdoOptionQueryReader implements OptionQueryReaderInterface
{
    private RowHydrator $row;

    public function __construct(
        private PDO $pdo,
        ClockInterface $clock,
    ) {
        $this->row = new RowHydrator($clock);
    }

    public function findById(int $optionId): ?OptionDTO
    {
        $statement = $this->pdo->prepare(
            'SELECT `id`, `product_id`, `code`, `status`, `display_order`, `created_at`, `updated_at`, `deleted_at` '
            . 'FROM `maa_product_options` WHERE `id` = :id LIMIT 1',
        );
        $statement->execute(['id' => $optionId]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $status = OptionStatusEnum::tryFrom(RowHydrator::string($row, 'status'));
        if ($status === null) {
            throw ProductStorageFailureFactory::invalidStorageValue('status');
        }

        return new OptionDTO(
            id: RowHydrator::positiveInt($row, 'id'),
            productId: RowHydrator::positiveInt($row, 'product_id'),
            code: RowHydrator::string($row, 'code'),
            status: $status,
            displayOrder: RowHydrator::positiveInt($row, 'display_order'),
            createdAt: $this->row->timestamp($row, 'created_at'),
            updatedAt: $this->row->timestamp($row, 'updated_at'),
            deletedAt: $this->row->nullableTimestamp($row, 'deleted_at'),
        );
    }
}
