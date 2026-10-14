<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\Consumer\Service;

use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Variant\Consumer\Criteria\VariantCompositionCriteriaDTO;
use Maatify\Product\Variant\Consumer\Criteria\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantCollectionDTO;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\Exception\VariantNotFoundException;
use Maatify\Product\Variant\Repository\VariantQueryReaderInterface;
use Maatify\Product\Variant\ValueObject\CompositionFingerprint;

/**
 * Serves the consumer reads and composition resolution of Variants. Reads write nothing and open
 * no transaction.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class VariantConsumerService implements VariantConsumerServiceInterface
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

    public function listByProductId(
        int $productId,
        VariantVisibleListCriteriaDTO $criteria = new VariantVisibleListCriteriaDTO(),
    ): VariantCollectionDTO {
        self::requirePositive($productId, 'productId');

        return new VariantCollectionDTO($this->reader->listVisible($productId, $criteria));
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

    private static function requirePositive(int $identity, string $field): void
    {
        if ($identity < 1) {
            throw ProductInvalidArgumentException::nonPositiveId($field);
        }
    }
}
