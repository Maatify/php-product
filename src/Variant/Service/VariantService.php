<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Service;

use Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Product\DTO\ProductConfigurationSnapshotDTO;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Product\Repository\ProductConfigurationRepositoryInterface;
use Maatify\Product\Product\Service\ProductConfigurationValidator;
use Maatify\Product\Variant\DTO\VariantCollectionDTO;
use Maatify\Product\Variant\DTO\VariantCompositionCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantStateDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\Enum\VariantStatusEnum;
use Maatify\Product\Variant\Exception\VariantNotFoundException;
use Maatify\Product\Variant\Repository\VariantCommandRepositoryInterface;
use Maatify\Product\Variant\Repository\VariantQueryReaderInterface;
use Maatify\Product\Variant\ValueObject\CompositionFingerprint;
use Maatify\SharedCommon\Contracts\ClockInterface;

/**
 * Orchestrates Variant reads, composition resolution, and lifecycle mutations.
 *
 * Reads write nothing and open no transaction. Lifecycle mutations run in the savepoint
 * transaction runner, lock the owning Product row, read the configuration under that lock,
 * validate the resulting graph in memory, and write only when the invariants hold.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class VariantService implements VariantServiceInterface
{
    public function __construct(
        private VariantQueryReaderInterface $reader,
        private VariantCommandRepositoryInterface $variants,
        private ProductConfigurationRepositoryInterface $configuration,
        private SavepointTransactionRunnerInterface $transactions,
        private ClockInterface $clock,
    ) {}

    public function getById(int $variantId): VariantDTO
    {
        self::requirePositive($variantId, 'variantId');
        $variant = $this->reader->findVisibleById($variantId);
        if ($variant === null) {
            throw VariantNotFoundException::withId($variantId);
        }

        return $variant;
    }

    public function getByIdForManagement(
        int $variantId,
        ProductDeletedStateEnum $deletedState = ProductDeletedStateEnum::NON_DELETED,
    ): VariantDTO {
        self::requirePositive($variantId, 'variantId');
        $variant = $this->reader->findById($variantId);
        $matches = $variant !== null && match ($deletedState) {
            ProductDeletedStateEnum::NON_DELETED => $variant->deletedAt === null,
            ProductDeletedStateEnum::DELETED_ONLY => $variant->deletedAt !== null,
            ProductDeletedStateEnum::INCLUDE_DELETED => true,
        };
        if ($variant === null || !$matches) {
            throw VariantNotFoundException::withId($variantId);
        }

        return $variant;
    }

    public function listByProductId(
        int $productId,
        VariantVisibleListCriteriaDTO $criteria = new VariantVisibleListCriteriaDTO(),
    ): VariantCollectionDTO {
        self::requirePositive($productId, 'productId');

        return new VariantCollectionDTO($this->reader->listVisible($productId, $criteria));
    }

    public function listByProductIdForManagement(
        int $productId,
        VariantListCriteriaDTO $criteria = new VariantListCriteriaDTO(),
    ): VariantCollectionDTO {
        self::requirePositive($productId, 'productId');

        return new VariantCollectionDTO($this->reader->listForManagement($productId, $criteria));
    }

    public function resolveByComposition(int $productId, VariantCompositionCriteriaDTO $criteria): VariantDTO
    {
        self::requirePositive($productId, 'productId');
        if (!$this->reader->isProductVisible($productId)) {
            throw ProductNotFoundException::withId($productId);
        }

        $options = [];
        foreach ($this->reader->listOptionStates($productId) as $option) {
            $options[$option->id] = $option;
        }
        $values = [];
        foreach ($this->reader->listOptionValueStates($productId) as $value) {
            $values[$value->id] = $value;
        }

        $selectedOptions = [];
        foreach ($criteria->selections as $selection) {
            $option = $options[$selection->optionId] ?? null;
            if ($option === null || $option->deleted || !$option->active) {
                throw ProductInvalidArgumentException::optionNotSelectable($selection->optionId, $productId);
            }
            $value = $values[$selection->optionValueId] ?? null;
            if ($value === null || $value->optionId !== $option->id || $value->deleted || !$value->active) {
                throw ProductInvalidArgumentException::optionValueNotSelectable($selection->optionValueId, $option->id);
            }
            $selectedOptions[$option->id] = true;
        }
        $missing = [];
        foreach ($options as $option) {
            if (!$option->deleted && $option->active && !isset($selectedOptions[$option->id])) {
                $missing[] = $option->id;
            }
        }
        if ($missing !== []) {
            throw ProductInvalidArgumentException::incompleteSelection($productId, $missing);
        }

        $fingerprint = CompositionFingerprint::forSelections($criteria->selections);
        $match = $this->reader->findActiveByFingerprint($productId, $fingerprint->value);
        if ($match === null) {
            throw VariantNotFoundException::forComposition($productId, $fingerprint->canonical);
        }
        if (CompositionFingerprint::forSelections($match->composition)->canonical !== $fingerprint->canonical) {
            throw ProductStorageFailureFactory::compositionFingerprintMismatch($match->id);
        }
        $visible = $this->reader->findVisibleById($match->id);
        if ($visible === null) {
            throw VariantNotFoundException::forComposition($productId, $fingerprint->canonical);
        }

        return $visible;
    }

    public function updateStatus(int $variantId, VariantStatusEnum $status): void
    {
        self::requirePositive($variantId, 'variantId');
        $occurredAt = $this->clock->now();

        $this->transactions->run(function () use ($variantId, $status, $occurredAt): void {
            [$snapshot, $variant] = $this->lockedVariant($variantId);
            $this->assertMutable($snapshot, $variant);
            $activate = $status === VariantStatusEnum::ACTIVE;
            if ($variant->active === $activate) {
                return;
            }
            self::assertValid($snapshot->withVariant($variant->withActive($activate)));
            if (!$this->variants->updateStatus($variantId, $status, $occurredAt)) {
                throw ProductStorageFailureFactory::lockedRowVanished('Variant', $variantId);
            }
        });
    }

    public function softDelete(int $variantId): void
    {
        self::requirePositive($variantId, 'variantId');
        $occurredAt = $this->clock->now();

        $this->transactions->run(function () use ($variantId, $occurredAt): void {
            [$snapshot, $variant] = $this->lockedVariant($variantId);
            $this->assertMutable($snapshot, $variant);
            self::assertValid($snapshot->withVariant($variant->withDeleted(true)));
            if (!$this->variants->softDelete($variantId, $occurredAt)) {
                throw ProductStorageFailureFactory::lockedRowVanished('Variant', $variantId);
            }
        });
    }

    public function restore(int $variantId): void
    {
        self::requirePositive($variantId, 'variantId');
        $occurredAt = $this->clock->now();

        $this->transactions->run(function () use ($variantId, $occurredAt): void {
            [$snapshot, $variant] = $this->lockedVariant($variantId);
            if ($snapshot->productDeleted) {
                throw ProductBusinessRuleException::ownerSoftDeleted('Variant', $variantId, 'Product', $snapshot->productId);
            }
            if (!$variant->deleted) {
                throw ProductBusinessRuleException::notSoftDeleted('Variant', $variantId);
            }
            self::assertValid($snapshot->withVariant($variant->withDeleted(false)));
            if (!$this->variants->restore($variantId, $occurredAt)) {
                throw ProductStorageFailureFactory::lockedRowVanished('Variant', $variantId);
            }
        });
    }

    /**
     * Locks the owning Product and returns the configuration snapshot with the Variant's state in it.
     *
     * @return array{ProductConfigurationSnapshotDTO, VariantStateDTO}
     */
    private function lockedVariant(int $variantId): array
    {
        $productId = $this->configuration->findVariantProductId($variantId);
        $snapshot = $productId === null ? null : $this->configuration->lockSnapshot($productId);
        $variant = $snapshot?->variants[$variantId] ?? null;
        if ($snapshot === null || $variant === null) {
            throw VariantNotFoundException::withId($variantId);
        }

        return [$snapshot, $variant];
    }

    private function assertMutable(ProductConfigurationSnapshotDTO $snapshot, VariantStateDTO $variant): void
    {
        if ($snapshot->productDeleted) {
            throw ProductBusinessRuleException::ownerSoftDeleted('Variant', $variant->id, 'Product', $snapshot->productId);
        }
        if ($variant->deleted) {
            throw ProductBusinessRuleException::softDeleted('Variant', $variant->id);
        }
    }

    private static function assertValid(ProductConfigurationSnapshotDTO $snapshot): void
    {
        $violation = ProductConfigurationValidator::firstViolation($snapshot);
        if ($violation !== null) {
            throw ProductBusinessRuleException::invalidConfiguration($snapshot->productId, $violation);
        }
    }

    private static function requirePositive(int $identity, string $field): void
    {
        if ($identity < 1) {
            throw ProductInvalidArgumentException::nonPositiveId($field);
        }
    }
}
