<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Service;

use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Variant\DTO\VariantCollectionDTO;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\Exception\VariantNotFoundException;
use Maatify\Product\Variant\Repository\VariantQueryReaderInterface;

/**
 * Orchestrates Variant reads. It writes nothing and opens no transaction.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class VariantService implements VariantServiceInterface
{
    public function __construct(private VariantQueryReaderInterface $reader) {}

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

    private static function requirePositive(int $identity, string $field): void
    {
        if ($identity < 1) {
            throw ProductInvalidArgumentException::nonPositiveId($field);
        }
    }
}
