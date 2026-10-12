<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Schema;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests against the real MySQL service provisioned by
 * scripts/ci/run-integration.sh. Each test class installs schema/product.sql into
 * its own fresh database; each test runs inside a transaction that is rolled back.
 * Missing configuration fails the test; nothing is skipped.
 */
abstract class SchemaTestCase extends TestCase
{
    protected const int DUPLICATE_KEY = 1062;
    protected const int FK_CHILD_MISSING_PARENT = 1452;
    protected const int FK_PARENT_REFERENCED = 1451;
    protected const int CHECK_VIOLATED = 3819;
    protected const int NO_DEFAULT = 1364;
    protected const int DATA_TOO_LONG = 1406;
    protected const int OUT_OF_RANGE = 1264;

    protected const string TS = '2026-01-01 00:00:00';

    protected static PDO $pdo;

    private static string $database = '';

    protected int $p1;
    protected int $p2;
    protected int $colorOption;
    protected int $sizeOption;
    protected int $colorOption2;
    protected int $red;
    protected int $blue;
    protected int $medium;
    protected int $red2;
    protected int $emptyVariant1;
    protected int $compositionVariant1;
    protected int $emptyVariant2;
    protected int $role;

    final public static function setUpBeforeClass(): void
    {
        self::$pdo = self::connect(null);
        self::$database = 'product_it_' . bin2hex(random_bytes(6));
        self::$pdo->exec('CREATE DATABASE `' . self::$database . '`');
        self::$pdo = self::connect(self::$database);

        $expected = self::env('PRODUCT_TEST_EXPECTED_VERSION');
        $actual = self::scalar('SELECT VERSION()');
        self::assertIsString($actual);
        self::assertStringStartsWith($expected, $actual, 'Connected to an unexpected MySQL server version.');

        self::installSchema();
    }

    final public static function tearDownAfterClass(): void
    {
        if (self::$database !== '') {
            self::$pdo = self::connect(null);
            self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::$database . '`');
            self::$database = '';
        }
    }

    final protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        $this->buildFixture();
    }

    final protected function tearDown(): void
    {
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
    }

    /** @return non-empty-string */
    protected static function env(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            self::fail(sprintf('Required Integration environment variable %s is not set.', $name));
        }

        return $value;
    }

    protected static function connect(?string $database): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4%s',
            self::env('PRODUCT_TEST_DB_HOST'),
            self::env('PRODUCT_TEST_DB_PORT'),
            $database === null ? '' : ';dbname=' . $database,
        );

        return new PDO($dsn, self::env('PRODUCT_TEST_DB_USER'), self::env('PRODUCT_TEST_DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
        ]);
    }

    protected static function schemaPath(): string
    {
        return dirname(__DIR__, 3) . '/schema/product.sql';
    }

    /** @return list<string> */
    protected static function schemaStatements(): array
    {
        $sql = file_get_contents(self::schemaPath());
        self::assertIsString($sql, 'schema/product.sql must be readable.');
        $withoutComments = preg_replace('/^--.*$/m', '', $sql);
        self::assertIsString($withoutComments);
        $statements = [];
        foreach (explode(";\n", $withoutComments) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    protected static function installSchema(): void
    {
        foreach (self::schemaStatements() as $statement) {
            self::$pdo->exec($statement);
        }
    }

    /** @param array<string, int|string|null> $params */
    protected static function scalar(string $sql, array $params = []): int|string|null
    {
        $statement = self::$pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_NUM);
        if (!is_array($row)) {
            return null;
        }
        $value = $row[0] ?? null;
        self::assertTrue($value === null || is_int($value) || is_string($value));

        return $value;
    }

    /** @param array<string, int|string|null> $params */
    protected function execute(string $sql, array $params = []): void
    {
        self::$pdo->prepare($sql)->execute($params);
    }

    /**
     * @param array<string, int|string|null> $row column => value; timestamps are added when absent
     */
    protected function insert(string $table, array $row): int
    {
        $row += ['created_at' => self::TS, 'updated_at' => self::TS];
        if (in_array($table, ['maa_product_variant_option_values'], true)) {
            unset($row['created_at'], $row['updated_at']);
        }
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', array_map(static fn (string $c): string => '`' . $c . '`', $columns)),
            implode(',', array_map(static fn (string $c): string => ':' . $c, $columns)),
        );
        $this->execute($sql, $row);

        return (int) self::$pdo->lastInsertId();
    }

    /** @param array<string, int|string|null> $params */
    protected function assertRejected(int $errno, string $sql, array $params = [], string $message = ''): void
    {
        try {
            $this->execute($sql, $params);
        } catch (PDOException $e) {
            $code = $e->errorInfo[1] ?? null;
            self::assertSame($errno, $code, ($message === '' ? '' : $message . ': ') . $e->getMessage());

            return;
        }
        self::fail(($message === '' ? '' : $message . ': ') . 'statement was accepted, expected MySQL error ' . $errno);
    }

    /** @param array<string, int|string|null> $row */
    protected function assertInsertRejected(int $errno, string $table, array $row, string $message = ''): void
    {
        try {
            $this->insert($table, $row);
        } catch (PDOException $e) {
            $code = $e->errorInfo[1] ?? null;
            self::assertSame($errno, $code, ($message === '' ? '' : $message . ': ') . $e->getMessage());

            return;
        }
        self::fail(($message === '' ? '' : $message . ': ') . 'insert into ' . $table . ' was accepted, expected MySQL error ' . $errno);
    }

    protected static function fingerprint(string $canonical): string
    {
        return hash('sha256', $canonical);
    }

    /** @param array<string, int|string|null> $overrides */
    protected function variant(int $productId, string $canonical, array $overrides = []): int
    {
        return $this->insert('maa_product_variants', $overrides + [
            'product_id' => $productId,
            'composition_fingerprint' => self::fingerprint($canonical),
            'status' => 'inactive',
            'display_order' => 50,
        ]);
    }

    /** @param array<string, int|string|null> $overrides */
    protected function assignment(array $overrides): int
    {
        return $this->insert('maa_product_media_assignments', $overrides + [
            'product_id' => $this->p1,
            'variant_id' => null,
            'media_asset_id' => 10,
            'role_id' => null,
            'language_code' => null,
            'platform' => null,
            'is_primary' => 0,
            'display_order' => 1,
        ]);
    }

    private function buildFixture(): void
    {
        $this->p1 = $this->insert('maa_product_products', ['code' => 'P-1']);
        $this->p2 = $this->insert('maa_product_products', ['code' => 'P-2']);
        $this->colorOption = $this->insert('maa_product_options', ['product_id' => $this->p1, 'code' => 'color', 'display_order' => 1]);
        $this->sizeOption = $this->insert('maa_product_options', ['product_id' => $this->p1, 'code' => 'size', 'display_order' => 2]);
        $this->colorOption2 = $this->insert('maa_product_options', ['product_id' => $this->p2, 'code' => 'color', 'display_order' => 1]);
        $this->red = $this->insert('maa_product_option_values', ['option_id' => $this->colorOption, 'code' => 'red', 'display_order' => 1]);
        $this->blue = $this->insert('maa_product_option_values', ['option_id' => $this->colorOption, 'code' => 'blue', 'display_order' => 2]);
        $this->medium = $this->insert('maa_product_option_values', ['option_id' => $this->sizeOption, 'code' => 'm', 'display_order' => 1]);
        $this->red2 = $this->insert('maa_product_option_values', ['option_id' => $this->colorOption2, 'code' => 'red', 'display_order' => 1]);
        $this->emptyVariant1 = $this->variant($this->p1, 'v1', ['sku' => 'SKU-1', 'barcode' => '0001', 'status' => 'active', 'display_order' => 1]);
        $this->compositionVariant1 = $this->variant($this->p1, 'v1|1=1', ['display_order' => 2]);
        $this->emptyVariant2 = $this->variant($this->p2, 'v1', ['status' => 'active', 'display_order' => 1]);
        $this->role = $this->insert('maa_product_media_roles', ['role_key' => 'hero']);
    }
}
