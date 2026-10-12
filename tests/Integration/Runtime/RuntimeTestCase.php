<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Runtime;

use DateTimeZone;
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\Product\Service\ProductServiceInterface;
use Maatify\Product\Tests\Integration\DatabaseTestCase;
use Maatify\SharedCommon\Infrastructure\SystemClock;

/**
 * Base for runtime tests: each test starts from empty tables and commits for real,
 * so the service's own transaction handling is exercised without an outer transaction.
 */
abstract class RuntimeTestCase extends DatabaseTestCase
{
    private const array TABLES_CHILD_FIRST = [
        'maa_product_media_assignments',
        'maa_product_media_roles',
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

    protected function productService(): ProductServiceInterface
    {
        return ProductFactory::createProductService(self::$pdo, new SystemClock(new DateTimeZone('UTC')));
    }

    protected static function rowCount(string $table, string $where = '1 = 1'): int
    {
        return (int) self::scalar('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where);
    }
}
