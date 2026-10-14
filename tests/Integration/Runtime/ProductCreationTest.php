<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Runtime;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Exception\ProductCodeAlreadyExistsException;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Product\Management\Command\CreateProductCommand;
use Maatify\Product\Product\Management\Service\ProductManagementService;
use Maatify\Product\Product\Repository\Pdo\PdoProductCommandRepository;
use Maatify\Product\Product\Repository\Pdo\PdoProductConfigurationRepository;
use Maatify\Product\Product\Repository\Pdo\PdoProductQueryReader;
use Maatify\Product\Tests\Support\UnusedVariantLifecycleWrites;
use Maatify\Product\Variant\Repository\VariantCommandRepositoryInterface;
use Maatify\Product\Variant\ValueObject\CompositionFingerprint;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use PDO;
use RuntimeException;

final class ProductCreationTest extends RuntimeTestCase
{
    public function testCreatesAnInactiveProductWithItsSingleActiveEmptyVariant(): void
    {
        $service = $this->productManagementService();

        $created = $service->create(new CreateProductCommand('SIMPLE-1'));

        $product = $service->getByIdForManagement($created->productId);
        self::assertSame('SIMPLE-1', $product->code);
        self::assertSame(ProductStatusEnum::INACTIVE, $product->status);
        self::assertNull($product->deletedAt);
        self::assertSame('UTC', $product->createdAt->getTimezone()->getName());
        self::assertEquals($product->createdAt, $product->updatedAt);

        self::assertSame(1, self::rowCount('maa_product_variants'));
        $statement = self::$pdo->query(
            'SELECT id, product_id, composition_fingerprint, sku, barcode, status, display_order, deleted_at FROM maa_product_variants',
        );
        self::assertNotFalse($statement);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertEquals($created->variantId, $row['id']);
        self::assertEquals($created->productId, $row['product_id']);
        self::assertSame(CompositionFingerprint::forEmptyComposition()->value, $row['composition_fingerprint']);
        self::assertNull($row['sku']);
        self::assertNull($row['barcode']);
        self::assertSame('active', $row['status']);
        self::assertEquals(1, $row['display_order']);
        self::assertNull($row['deleted_at']);
        self::assertSame(0, self::rowCount('maa_product_options'));
        self::assertSame(0, self::rowCount('maa_product_variant_option_values'));
    }

    public function testEachProductGetsItsOwnVariantIdentity(): void
    {
        $service = $this->productManagementService();

        $first = $service->create(new CreateProductCommand('A'));
        $second = $service->create(new CreateProductCommand('B'));

        self::assertNotSame($first->variantId, $second->variantId);
        self::assertSame(2, self::rowCount('maa_product_variants'));
        self::assertSame(2, self::rowCount('maa_product_variants', 'display_order = 1'));
    }

    public function testDuplicateCodeConflictsCaseInsensitivelyAndLeavesNothingBehind(): void
    {
        $service = $this->productManagementService();
        $service->create(new CreateProductCommand('P-1'));

        foreach (['P-1', 'p-1'] as $code) {
            try {
                $service->create(new CreateProductCommand($code));
                self::fail('A duplicate code must conflict: ' . $code);
            } catch (ProductCodeAlreadyExistsException $e) {
                self::assertInstanceOf(\PDOException::class, $e->getPrevious());
            }
        }

        self::assertSame(1, self::rowCount('maa_product_products'));
        self::assertSame(1, self::rowCount('maa_product_variants'));
    }

    public function testASoftDeletedProductKeepsItsCodeReservedAndIsNeverRestoredByCreate(): void
    {
        $service = $this->productManagementService();
        $created = $service->create(new CreateProductCommand('P-DEL'));
        $this->execute('UPDATE maa_product_products SET deleted_at = :t WHERE id = :i', ['t' => '2026-01-01 00:00:00', 'i' => $created->productId]);

        foreach (['P-DEL', 'p-del'] as $code) {
            try {
                $service->create(new CreateProductCommand($code));
                self::fail('Create must conflict with the soft-deleted identity: ' . $code);
            } catch (ProductCodeAlreadyExistsException $conflict) {
                self::assertStringContainsString($code, $conflict->getMessage(), 'The conflict identifies the colliding code.');
                self::assertInstanceOf(\PDOException::class, $conflict->getPrevious());
            }
        }

        self::assertSame(1, self::rowCount('maa_product_products'), 'No second row is inserted.');
        self::assertSame(1, self::rowCount('maa_product_variants'), 'No Variant is created for the rejected attempts.');
        self::assertSame(1, self::rowCount('maa_product_products', 'deleted_at IS NOT NULL'), 'Create never restores the soft-deleted identity.');
    }

    public function testManagementReadReturnsSoftDeletedProductsOnlyWhenAskedToAndHydratesInTheHostTimezone(): void
    {
        $service = $this->productManagementService();
        $created = $service->create(new CreateProductCommand('P-DEL'));
        $this->execute('UPDATE maa_product_products SET deleted_at = :t WHERE id = :i', ['t' => '2026-02-03 04:05:06', 'i' => $created->productId]);

        $product = $service->getByIdForManagement($created->productId, ProductDeletedStateEnum::INCLUDE_DELETED);

        self::assertNotNull($product->deletedAt);
        self::assertSame('2026-02-03 04:05:06', $product->deletedAt->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $product->deletedAt->getTimezone()->getName());
        self::assertSame($product->id, $service->getByIdForManagement($created->productId, ProductDeletedStateEnum::DELETED_ONLY)->id);

        $this->expectException(ProductNotFoundException::class);
        $service->getByIdForManagement($created->productId);
    }

    public function testManagementReadExcludesDeletedOnlyWhenNotDeleted(): void
    {
        $service = $this->productManagementService();
        $created = $service->create(new CreateProductCommand('P-LIVE'));

        self::assertSame($created->productId, $service->getByIdForManagement($created->productId)->id);
        self::assertSame($created->productId, $service->getByIdForManagement($created->productId, ProductDeletedStateEnum::INCLUDE_DELETED)->id);

        $this->expectException(ProductNotFoundException::class);
        $service->getByIdForManagement($created->productId, ProductDeletedStateEnum::DELETED_ONLY);
    }

    public function testConsumerReadRequiresAnActiveNonDeletedProduct(): void
    {
        $service = $this->productManagementService();
        $consumer = $this->productConsumerService();
        $created = $service->create(new CreateProductCommand('P-VIS'));

        try {
            $consumer->getById($created->productId);
            self::fail('A new inactive Product is not consumer-visible.');
        } catch (ProductNotFoundException) {
        }

        $this->execute("UPDATE maa_product_products SET status = 'active' WHERE id = :i", ['i' => $created->productId]);
        self::assertSame($created->productId, $consumer->getById($created->productId)->id);

        $this->execute('UPDATE maa_product_products SET deleted_at = :t WHERE id = :i', ['t' => '2026-02-03 04:05:06', 'i' => $created->productId]);
        $this->expectException(ProductNotFoundException::class);
        $consumer->getById($created->productId);
    }

    public function testReadsThrowNotFoundForAnUnknownIdentity(): void
    {
        $service = $this->productManagementService();
        $consumer = $this->productConsumerService();

        foreach ([static fn() => $consumer->getById(999999), static fn() => $service->getByIdForManagement(999999, ProductDeletedStateEnum::INCLUDE_DELETED)] as $read) {
            try {
                $read();
                self::fail('An unknown identity must not be found.');
            } catch (ProductNotFoundException) {
            }
        }
        $this->addToAssertionCount(1);
    }

    public function testAFailureAfterTheProductWriteRollsTheWholeOperationBack(): void
    {
        $failure = new RuntimeException('variant write failed');
        $failingVariants = new class ($failure) implements VariantCommandRepositoryInterface {
            use UnusedVariantLifecycleWrites;

            public function __construct(private RuntimeException $failure) {}

            public function createEmptyComposition(int $productId, DateTimeImmutable $occurredAt): int
            {
                throw $this->failure;
            }
        };
        $service = new ProductManagementService(
            new PdoProductCommandRepository(self::$pdo),
            $failingVariants,
            new PdoProductQueryReader(self::$pdo, new SystemClock(new DateTimeZone('UTC'))),
            new PdoProductConfigurationRepository(self::$pdo, new ScopedOrderingManager()),
            new PdoSavepointTransactionRunner(self::$pdo),
            new SystemClock(new DateTimeZone('UTC')),
        );

        try {
            $service->create(new CreateProductCommand('P-ROLLBACK'));
            self::fail('The failure must propagate.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame(0, self::rowCount('maa_product_products'), 'The Product insert must be rolled back.');
        self::assertFalse(self::$pdo->inTransaction());
    }

    public function testJoinsACallerOwnedTransactionAndLeavesCommitToTheCaller(): void
    {
        $service = $this->productManagementService();

        self::$pdo->beginTransaction();
        $service->create(new CreateProductCommand('P-OUTER-ROLLBACK'));
        self::assertTrue(self::$pdo->inTransaction(), 'The service must not commit the caller transaction.');
        self::$pdo->rollBack();
        self::assertSame(0, self::rowCount('maa_product_products'));
        self::assertSame(0, self::rowCount('maa_product_variants'));

        self::$pdo->beginTransaction();
        $service->create(new CreateProductCommand('P-OUTER-COMMIT'));
        self::$pdo->commit();
        self::assertSame(1, self::rowCount('maa_product_products'));
        self::assertSame(1, self::rowCount('maa_product_variants'));
    }

    public function testVariantOrderIsAllocatedThroughTheSharedOrderingApi(): void
    {
        $service = $this->productManagementService();
        $created = $service->create(new CreateProductCommand('P-ORDER'));
        $this->execute(
            'INSERT INTO maa_product_variants (product_id, composition_fingerprint, status, display_order, created_at, updated_at) VALUES (:p, :f, :s, 7, :t, :t2)',
            ['p' => $created->productId, 'f' => hash('sha256', 'v1|9=9'), 's' => 'inactive', 't' => '2026-01-01 00:00:00', 't2' => '2026-01-01 00:00:00'],
        );

        $next = (new ScopedOrderingManager())->getNextPosition(
            self::$pdo,
            new \Maatify\Persistence\Pdo\Ordering\ScopedOrderingConfig('maa_product_variants', 'product_id'),
            $created->productId,
        );

        self::assertSame(8, $next, 'Variant display_order is a positive position in the Product scope.');
    }

    public function testTheFactoryRejectsAConnectionThatDoesNotThrow(): void
    {
        $silent = self::connect(self::$database);
        $silent->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $this->expectException(ProductInvalidArgumentException::class);
        ProductFactory::createProductManagementService($silent, new SystemClock(new DateTimeZone('UTC')));
    }
}
