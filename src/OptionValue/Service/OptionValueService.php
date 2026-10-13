<?php

declare(strict_types=1);

namespace Maatify\Product\OptionValue\Service;

use Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Option\Exception\OptionNotFoundException;
use Maatify\Product\Option\Repository\OptionCommandRepositoryInterface;
use Maatify\Product\OptionValue\Command\CreateOptionValueCommand;
use Maatify\Product\OptionValue\DTO\CreatedOptionValueDTO;
use Maatify\Product\OptionValue\DTO\OptionValueDTO;
use Maatify\Product\OptionValue\DTO\OptionValueStateDTO;
use Maatify\Product\OptionValue\Exception\OptionValueNotFoundException;
use Maatify\Product\OptionValue\Repository\OptionValueCommandRepositoryInterface;
use Maatify\Product\OptionValue\Repository\OptionValueQueryReaderInterface;
use Maatify\Product\Product\DTO\ProductConfigurationSnapshotDTO;
use Maatify\Product\Product\Repository\ProductCommandRepositoryInterface;
use Maatify\Product\Product\Repository\ProductConfigurationRepositoryInterface;
use Maatify\Product\Product\Service\ProductConfigurationValidator;
use Maatify\SharedCommon\Contracts\ClockInterface;

/**
 * Orchestrates Option Value creation, lifecycle, and management lookup under the Product lock.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class OptionValueService implements OptionValueServiceInterface
{
    public function __construct(
        private OptionValueCommandRepositoryInterface $values,
        private OptionValueQueryReaderInterface $reader,
        private OptionCommandRepositoryInterface $options,
        private ProductCommandRepositoryInterface $products,
        private ProductConfigurationRepositoryInterface $configuration,
        private SavepointTransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function create(CreateOptionValueCommand $command): CreatedOptionValueDTO
    {
        $occurredAt = $this->clock->now();

        return $this->transactions->run(function () use ($command, $occurredAt): CreatedOptionValueDTO {
            $productId = $this->options->findProductId($command->optionId);
            if ($productId === null) {
                throw OptionNotFoundException::withId($command->optionId);
            }
            $product = $this->products->lockForUpdate($productId);
            $option = $this->options->lockForUpdate($command->optionId);
            if ($product === null || $option === null) {
                throw OptionNotFoundException::withId($command->optionId);
            }
            if ($product->deleted) {
                throw ProductBusinessRuleException::ownerSoftDeletedForCreate('Option Value', 'Product', $productId);
            }
            if ($option->deleted) {
                throw ProductBusinessRuleException::ownerSoftDeletedForCreate('Option Value', 'Option', $command->optionId);
            }

            return new CreatedOptionValueDTO($this->values->create($command, $occurredAt));
        });
    }

    public function softDelete(int $optionValueId): void
    {
        self::requirePositive($optionValueId);
        $occurredAt = $this->clock->now();

        $this->transactions->run(function () use ($optionValueId, $occurredAt): void {
            [$snapshot, $value] = $this->lockedValue($optionValueId);
            $this->assertOwnersLive($snapshot, $value);
            if ($value->deleted) {
                throw ProductBusinessRuleException::softDeleted('Option Value', $optionValueId);
            }
            self::assertValid($snapshot->withOptionValue($value->withDeleted(true)));
            if (!$this->values->softDelete($optionValueId, $occurredAt)) {
                throw ProductStorageFailureFactory::lockedRowVanished('Option Value', $optionValueId);
            }
        });
    }

    public function restore(int $optionValueId): void
    {
        self::requirePositive($optionValueId);
        $occurredAt = $this->clock->now();

        $this->transactions->run(function () use ($optionValueId, $occurredAt): void {
            [$snapshot, $value] = $this->lockedValue($optionValueId);
            $this->assertOwnersLive($snapshot, $value);
            if (!$value->deleted) {
                throw ProductBusinessRuleException::notSoftDeleted('Option Value', $optionValueId);
            }
            self::assertValid($snapshot->withOptionValue($value->withDeleted(false)));
            if (!$this->values->restore($optionValueId, $occurredAt)) {
                throw ProductStorageFailureFactory::lockedRowVanished('Option Value', $optionValueId);
            }
        });
    }

    public function getByIdForManagement(
        int $optionValueId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): OptionValueDTO {
        self::requirePositive($optionValueId);
        $value = $this->reader->findById($optionValueId);
        $matches = $value !== null && match ($deletedState) {
            ProductDeletedStateEnum::NON_DELETED => $value->deletedAt === null,
            ProductDeletedStateEnum::DELETED_ONLY => $value->deletedAt !== null,
            ProductDeletedStateEnum::INCLUDE_DELETED => true,
        };
        if ($value === null || !$matches) {
            throw OptionValueNotFoundException::withId($optionValueId);
        }

        return $value;
    }

    /**
     * @return array{ProductConfigurationSnapshotDTO, OptionValueStateDTO}
     */
    private function lockedValue(int $optionValueId): array
    {
        $productId = $this->values->findProductId($optionValueId);
        $snapshot = $productId === null ? null : $this->configuration->lockSnapshot($productId);
        $value = $snapshot?->optionValues[$optionValueId] ?? null;
        if ($snapshot === null || $value === null) {
            throw OptionValueNotFoundException::withId($optionValueId);
        }

        return [$snapshot, $value];
    }

    private function assertOwnersLive(ProductConfigurationSnapshotDTO $snapshot, OptionValueStateDTO $value): void
    {
        if ($snapshot->productDeleted) {
            throw ProductBusinessRuleException::ownerSoftDeleted('Option Value', $value->id, 'Product', $snapshot->productId);
        }
        $option = $snapshot->options[$value->optionId] ?? null;
        if ($option === null || $option->deleted) {
            throw ProductBusinessRuleException::ownerSoftDeleted('Option Value', $value->id, 'Option', $value->optionId);
        }
    }

    private static function assertValid(ProductConfigurationSnapshotDTO $snapshot): void
    {
        $violation = ProductConfigurationValidator::firstViolation($snapshot);
        if ($violation !== null) {
            throw ProductBusinessRuleException::invalidConfiguration($snapshot->productId, $violation);
        }
    }

    private static function requirePositive(int $optionValueId): void
    {
        if ($optionValueId < 1) {
            throw ProductInvalidArgumentException::nonPositiveId('optionValueId');
        }
    }
}
