<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Runtime;

use DateTimeZone;
use Maatify\Product\Content\Service\OptionContentServiceInterface;
use Maatify\Product\Content\Service\OptionValueContentServiceInterface;
use Maatify\Product\Content\Service\ProductContentServiceInterface;
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\ImageAssignment\Service\ImageAssignmentServiceInterface;
use Maatify\Product\ImageRole\Service\ImageRoleServiceInterface;
use Maatify\Product\Option\Command\CreateOptionCommand;
use Maatify\Product\Option\Service\OptionServiceInterface;
use Maatify\Product\OptionValue\Command\CreateOptionValueCommand;
use Maatify\Product\OptionValue\Service\OptionValueServiceInterface;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\Command\ReconfigureOptionTarget;
use Maatify\Product\Product\Command\ReconfigureProductCommand;
use Maatify\Product\Product\Command\ReconfigureVariantTarget;
use Maatify\Product\Product\DTO\CreatedProductDTO;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Service\ProductServiceInterface;
use Maatify\Product\Tests\Integration\DatabaseTestCase;
use Maatify\Product\Variant\Service\VariantServiceInterface;
use Maatify\Product\Variant\ValueObject\OptionValueSelection;
use Maatify\SharedCommon\Contracts\ClockInterface;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Base for runtime tests: each test starts from empty tables and commits for real,
 * so the service's own transaction handling is exercised without an outer transaction.
 *
 * Content has no write command yet, so labels and Product names are seeded by direct SQL;
 * everything else goes through the public API.
 */
abstract class RuntimeTestCase extends DatabaseTestCase
{
    protected const string SEED_TS = '2026-01-01 00:00:00';
    public const string LATER_TS = '2099-01-01 00:00:00';

    private const array TABLES_CHILD_FIRST = [
        'maa_product_image_assignments',
        'maa_product_image_roles',
        'maa_product_variant_option_values',
        'maa_product_variants',
        'maa_product_option_value_contents',
        'maa_product_option_values',
        'maa_product_option_contents',
        'maa_product_options',
        'maa_product_product_contents',
        'maa_product_products',
    ];

    final protected function setUp(): void
    {
        foreach (self::TABLES_CHILD_FIRST as $table) {
            self::$pdo->exec('DELETE FROM `' . $table . '`');
        }
    }

    protected static function clock(): SystemClock
    {
        return new SystemClock(new DateTimeZone('UTC'));
    }

    /**
     * A Host Clock fixed at {@see self::LATER_TS}, past every instant the setup can stamp, so any
     * write made through a service built with it carries a later `updated_at` than the setup left.
     */
    protected static function laterClock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(RuntimeTestCase::LATER_TS, new DateTimeZone('UTC'));
            }

            public function getTimezone(): DateTimeZone
            {
                return new DateTimeZone('UTC');
            }
        };
    }

    /**
     * Proves that the call writes nothing to the table: every row, `updated_at` included, is
     * identical before and after. Run the call through a service built with {@see self::laterClock()}
     * so that a write landing within the same second as the setup is still detected.
     */
    protected function assertNoWrite(string $table, callable $call, string $message): void
    {
        $before = self::tableRows($table);
        $call();
        self::assertSame($before, self::tableRows($table), $message);
    }

    /** @return list<array<string, mixed>> */
    private static function tableRows(string $table): array
    {
        $statement = self::$pdo->query('SELECT * FROM `' . $table . '` ORDER BY `id`');
        self::assertNotFalse($statement);

        /** @var list<array<string, mixed>> */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function productService(?ClockInterface $clock = null): ProductServiceInterface
    {
        return ProductFactory::createProductService(self::$pdo, $clock ?? self::clock());
    }

    protected function variantService(?ClockInterface $clock = null): VariantServiceInterface
    {
        return ProductFactory::createVariantService(self::$pdo, $clock ?? self::clock());
    }

    protected function optionService(?ClockInterface $clock = null): OptionServiceInterface
    {
        return ProductFactory::createOptionService(self::$pdo, $clock ?? self::clock());
    }

    protected function optionValueService(?ClockInterface $clock = null): OptionValueServiceInterface
    {
        return ProductFactory::createOptionValueService(self::$pdo, $clock ?? self::clock());
    }

    protected function imageRoleService(?ClockInterface $clock = null): ImageRoleServiceInterface
    {
        return ProductFactory::createImageRoleService(self::$pdo, $clock ?? self::clock());
    }

    protected function imageAssignmentService(?ClockInterface $clock = null): ImageAssignmentServiceInterface
    {
        return ProductFactory::createImageAssignmentService(self::$pdo, $clock ?? self::clock());
    }

    protected function productContentService(?ClockInterface $clock = null): ProductContentServiceInterface
    {
        return ProductFactory::createProductContentService(self::$pdo, $clock ?? self::clock());
    }

    protected function optionContentService(?ClockInterface $clock = null): OptionContentServiceInterface
    {
        return ProductFactory::createOptionContentService(self::$pdo, $clock ?? self::clock());
    }

    protected function optionValueContentService(?ClockInterface $clock = null): OptionValueContentServiceInterface
    {
        return ProductFactory::createOptionValueContentService(self::$pdo, $clock ?? self::clock());
    }

    protected static function rowCount(string $table, string $where = '1 = 1'): int
    {
        return (int) self::scalar('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where);
    }

    /**
     * Creates a Product through the public API and seeds an unlocalized name for it.
     */
    protected function namedProduct(string $code): CreatedProductDTO
    {
        $created = $this->productService()->create(new CreateProductCommand($code));
        $this->nameProduct($created->productId, 'Name of ' . $code);

        return $created;
    }

    protected function nameProduct(int $productId, string $name): void
    {
        $this->execute(
            'INSERT INTO maa_product_product_contents (product_id, language_code, name, created_at, updated_at) VALUES (:p, NULL, :n, :t, :u)',
            ['p' => $productId, 'n' => $name, 't' => self::SEED_TS, 'u' => self::SEED_TS],
        );
    }

    /**
     * Creates an inactive Option through the public API and seeds its label.
     */
    protected function preparedOption(int $productId, string $code, ?string $label = null): int
    {
        $optionId = $this->optionService()->create(new CreateOptionCommand($productId, $code))->optionId;
        if ($label !== null) {
            $this->labelOption($optionId, $label);
        }

        return $optionId;
    }

    protected function labelOption(int $optionId, string $label): void
    {
        $this->execute(
            'INSERT INTO maa_product_option_contents (option_id, language_code, name, created_at, updated_at) VALUES (:i, NULL, :n, :t, :u)',
            ['i' => $optionId, 'n' => $label, 't' => self::SEED_TS, 'u' => self::SEED_TS],
        );
    }

    /**
     * Creates an inactive Option Value through the public API and seeds its label.
     */
    protected function preparedValue(int $optionId, string $code, ?string $label = null): int
    {
        $valueId = $this->optionValueService()->create(new CreateOptionValueCommand($optionId, $code))->optionValueId;
        if ($label !== null) {
            $this->labelValue($valueId, $label);
        }

        return $valueId;
    }

    protected function labelValue(int $valueId, string $label): void
    {
        $this->execute(
            'INSERT INTO maa_product_option_value_contents (option_value_id, language_code, name, created_at, updated_at) VALUES (:i, NULL, :n, :t, :u)',
            ['i' => $valueId, 'n' => $label, 't' => self::SEED_TS, 'u' => self::SEED_TS],
        );
    }

    /**
     * Builds a reconfiguration command from plain identities.
     *
     * @param array<int, list<int>> $options optionId => active value ids
     * @param list<array{0: list<array{int, int}>, 1?: int|null}> $variants each: [[optionId, valueId], ...] and an optional reuse identity
     */
    protected static function reconfiguration(int $productId, array $options, array $variants): ReconfigureProductCommand
    {
        $optionTargets = [];
        foreach ($options as $optionId => $valueIds) {
            $optionTargets[] = new ReconfigureOptionTarget($optionId, $valueIds);
        }
        $variantTargets = [];
        foreach ($variants as $variant) {
            $variantTargets[] = new ReconfigureVariantTarget(
                array_map(static fn(array $pair): OptionValueSelection => new OptionValueSelection($pair[0], $pair[1]), $variant[0]),
                $variant[1] ?? null,
            );
        }

        return new ReconfigureProductCommand($productId, $optionTargets, $variantTargets);
    }

    protected function activateProduct(int $productId): void
    {
        $this->productService()->updateStatus($productId, ProductStatusEnum::ACTIVE);
    }

    /**
     * @param class-string<Throwable> $exception
     */
    protected function assertThrows(string $exception, callable $call, string $message = ''): Throwable
    {
        try {
            $call();
        } catch (Throwable $caught) {
            self::assertInstanceOf($exception, $caught, $message !== '' ? $message : 'Unexpected exception: ' . $caught::class . ': ' . $caught->getMessage());

            return $caught;
        }
        self::fail($message !== '' ? $message : 'Expected ' . $exception . ' was not raised.');
    }
}
