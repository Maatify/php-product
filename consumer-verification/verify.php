<?php

declare(strict_types=1);

/**
 * Consumer Verification Harness for maatify/php-product.
 *
 * Runs in an external Composer root that installed the package as a dependency. It
 * loads the package only through the production PSR-4 autoloader, uses only the
 * documented public API (ProductFactory, CreateProductCommand, ProductServiceInterface,
 * the public DTOs and exceptions) and the schema asset delivered with the installed
 * package, and drives a real MySQL service. It never includes package source files,
 * test classes, or autoload-dev.
 */

use Composer\InstalledVersions;
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Exception\ProductCodeAlreadyExistsException;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Product\Service\ProductServiceInterface;
use Maatify\SharedCommon\Infrastructure\SystemClock;

require __DIR__ . '/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "ok: {$message}\n";
}

function env(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        fwrite(STDERR, "FAIL: missing environment variable {$name}\n");
        exit(1);
    }

    return $value;
}

check(InstalledVersions::isInstalled('maatify/php-product'), 'package is installed through Composer');
$installPath = InstalledVersions::getInstallPath('maatify/php-product');
check(is_string($installPath) && is_dir($installPath), 'installed package path exists');
check(!is_link((string) $installPath), 'package was copied, not symlinked from the repository');

// Production PSR-4 autoload: the public classes resolve from the installed package.
$installRoot = (string) realpath((string) $installPath);
foreach ([ProductFactory::class, CreateProductCommand::class, ProductServiceInterface::class, ProductStatusEnum::class] as $class) {
    $file = (new ReflectionClass($class))->getFileName();
    check(
        is_string($file) && str_starts_with((string) realpath($file), $installRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR),
        "{$class} is autoloaded from the installed package",
    );
}
check(!class_exists('Maatify\\Product\\Tests\\Integration\\DatabaseTestCase'), 'test namespaces are not autoloadable by the consumer');

$schemaFile = $installPath . '/schema/product.sql';
check(is_file($schemaFile), 'schema/product.sql is delivered with the installed package');
$sql = (string) file_get_contents($schemaFile);
$statements = [];
foreach (explode(";\n", (string) preg_replace('/^--.*$/m', '', $sql)) as $statement) {
    if (trim($statement) !== '') {
        $statements[] = trim($statement);
    }
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$base = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('PRODUCT_TEST_DB_HOST'), env('PRODUCT_TEST_DB_PORT'));
$admin = new PDO($base, env('PRODUCT_TEST_DB_USER'), env('PRODUCT_TEST_DB_PASSWORD'), $options);
$database = 'product_consumer_' . bin2hex(random_bytes(6));
$admin->exec("CREATE DATABASE `{$database}`");

try {
    $pdo = new PDO($base . ';dbname=' . $database, env('PRODUCT_TEST_DB_USER'), env('PRODUCT_TEST_DB_PASSWORD'), $options);
    $version = $pdo->query('SELECT VERSION()');
    check($version !== false && str_starts_with((string) $version->fetchColumn(), env('PRODUCT_TEST_EXPECTED_VERSION')), 'connected to the expected MySQL version');

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    $tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'maa\\_product\\_%'");
    check($tables !== false && (int) $tables->fetchColumn() === 10, 'the installed schema creates the ten Product tables');

    // Realistic workflow: Host input -> public API -> service -> database -> observable result.
    $service = ProductFactory::createProductService($pdo, new SystemClock(new DateTimeZone('UTC')));

    $created = $service->create(new CreateProductCommand('SIMPLE-1'));
    check($created->productId > 0 && $created->variantId > 0, 'creating a Product returns its Product and Variant identities');

    $product = $service->getByIdForManagement($created->productId);
    check($product->code === 'SIMPLE-1', 'the stored Product is readable through the public API');
    check($product->status === ProductStatusEnum::INACTIVE, 'a new Product is inactive');
    check($product->deletedAt === null, 'a new Product is not soft deleted');

    $variants = $pdo->prepare('SELECT status, display_order FROM maa_product_variants WHERE product_id = ?');
    $variants->execute([$created->productId]);
    $rows = $variants->fetchAll(PDO::FETCH_NUM);
    check($rows === [['active', 1]], 'the Product has exactly one active empty-composition Variant');

    $second = $service->create(new CreateProductCommand('SIMPLE-2'));
    check($second->variantId !== $created->variantId, 'each Product receives its own Variant identity');

    try {
        $service->create(new CreateProductCommand('simple-1'));
        check(false, 'a duplicate code must conflict');
    } catch (ProductCodeAlreadyExistsException) {
        check(true, 'a duplicate code conflicts case-insensitively');
    }
    $count = $pdo->query('SELECT COUNT(*) FROM maa_product_products');
    check($count !== false && (int) $count->fetchColumn() === 2, 'the conflicting create left no extra Product behind');

    try {
        $service->getById($created->productId);
        check(false, 'an inactive Product must not be consumer-visible');
    } catch (ProductNotFoundException) {
        check(true, 'an inactive Product is hidden from the consumer read');
    }

    try {
        $service->getById(999999);
        check(false, 'an unknown Product must not be found');
    } catch (ProductNotFoundException) {
        check(true, 'an unknown Product raises the not-found exception');
    }
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "Consumer verification passed.\n";
