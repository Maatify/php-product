<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Unit\Variant\Service;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Variant\DTO\VariantDTO;
use Maatify\Product\Variant\DTO\VariantListCriteriaDTO;
use Maatify\Product\Variant\DTO\VariantVisibleListCriteriaDTO;
use Maatify\Product\Variant\Enum\VariantStatusEnum;
use Maatify\Product\Variant\Exception\VariantNotFoundException;
use Maatify\Product\Variant\Repository\VariantQueryReaderInterface;
use Maatify\Product\Variant\Service\VariantService;
use PHPUnit\Framework\TestCase;

final class VariantServiceTest extends TestCase
{
    public function testGetByIdUsesTheVisibleReadOnly(): void
    {
        $live = $this->variant(null);
        $reader = $this->reader(visible: $live, all: null);

        self::assertSame($live, (new VariantService($reader))->getById(5));
    }

    public function testGetByIdThrowsNotFoundWhenTheVariantIsNotVisible(): void
    {
        $reader = $this->reader(visible: null, all: $this->variant(null));

        $this->expectException(VariantNotFoundException::class);
        (new VariantService($reader))->getById(5);
    }

    public function testManagementReadFiltersOnlyByDeletedState(): void
    {
        $live = $this->variant(null);
        $deleted = $this->variant(new DateTimeImmutable('2026-01-02'));

        self::assertSame($live, (new VariantService($this->reader(null, $live)))->getByIdForManagement(5));
        self::assertSame($deleted, (new VariantService($this->reader(null, $deleted)))->getByIdForManagement(5, ProductDeletedStateEnum::DELETED_ONLY));
        self::assertSame($deleted, (new VariantService($this->reader(null, $deleted)))->getByIdForManagement(5, ProductDeletedStateEnum::INCLUDE_DELETED));

        foreach ([[$deleted, ProductDeletedStateEnum::NON_DELETED], [$live, ProductDeletedStateEnum::DELETED_ONLY]] as [$variant, $state]) {
            try {
                (new VariantService($this->reader(null, $variant)))->getByIdForManagement(5, $state);
                self::fail('The deleted-state filter must exclude the Variant.');
            } catch (VariantNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testListsDelegateAndWrapTheReaderResult(): void
    {
        $variant = $this->variant(null);
        $service = new VariantService($this->reader($variant, $variant, [$variant]));

        self::assertSame([$variant], $service->listByProductId(1)->items());
        self::assertSame([$variant], $service->listByProductIdForManagement(1, new VariantListCriteriaDTO(VariantStatusEnum::ACTIVE))->items());
        self::assertCount(1, $service->listByProductId(1, new VariantVisibleListCriteriaDTO(1)));
    }

    public function testNonPositiveIdentitiesAreRejectedBeforeAnyRead(): void
    {
        $service = new VariantService($this->reader(null, null));

        foreach ([
            static fn() => $service->getById(0),
            static fn() => $service->getByIdForManagement(0),
            static fn() => $service->listByProductId(-3),
            static fn() => $service->listByProductIdForManagement(0),
        ] as $call) {
            try {
                $call();
                self::fail('A non-positive identity must be rejected.');
            } catch (ProductInvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function variant(?DateTimeImmutable $deletedAt): VariantDTO
    {
        $at = new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'));

        return new VariantDTO(5, 1, null, null, VariantStatusEnum::ACTIVE, 1, [], $at, $at, $deletedAt);
    }

    /** @param list<VariantDTO> $list */
    private function reader(?VariantDTO $visible, ?VariantDTO $all, array $list = []): VariantQueryReaderInterface
    {
        return new class ($visible, $all, $list) implements VariantQueryReaderInterface {
            /** @param list<VariantDTO> $list */
            public function __construct(private ?VariantDTO $visible, private ?VariantDTO $all, private array $list) {}

            public function findById(int $variantId): ?VariantDTO
            {
                return $this->all;
            }

            public function findVisibleById(int $variantId): ?VariantDTO
            {
                return $this->visible;
            }

            public function listForManagement(int $productId, VariantListCriteriaDTO $criteria): array
            {
                return $this->list;
            }

            public function listVisible(int $productId, VariantVisibleListCriteriaDTO $criteria): array
            {
                return $this->list;
            }
        };
    }
}
