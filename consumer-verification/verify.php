<?php

declare(strict_types=1);

/**
 * Consumer Verification Harness for maatify/php-product.
 *
 * Runs in an external Composer root that installed the package as a dependency. It
 * loads the package only through the production PSR-4 autoloader, uses only the
 * documented public API (the factory, the service interfaces, the commands, the public DTOs
 * and exceptions) and the schema asset delivered with the installed package, and drives a
 * real MySQL service. It loads nothing from the package repository itself: no source file,
 * no test class, no development autoloader.
 * Content has no command yet, so the Option and Value labels this workflow needs are inserted
 * directly into the harness database.
 */

use Composer\InstalledVersions;
use Maatify\Product\Common\Exception\ProductBusinessRuleException;
use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\ImageAssignment\Command\CreateImageAssignmentCommand;
use Maatify\Product\ImageRole\Command\CreateImageRoleCommand;
use Maatify\Product\Option\Command\CreateOptionCommand;
use Maatify\Product\OptionValue\Command\CreateOptionValueCommand;
use Maatify\Product\Product\Command\CreateProductCommand;
use Maatify\Product\Product\Command\ReconfigureOptionTarget;
use Maatify\Product\Product\Command\ReconfigureProductCommand;
use Maatify\Product\Product\Command\ReconfigureVariantTarget;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Exception\ProductCodeAlreadyExistsException;
use Maatify\Product\Product\Exception\ProductNotFoundException;
use Maatify\Product\Product\Service\ProductServiceInterface;
use Maatify\Product\Variant\DTO\VariantCompositionCriteriaDTO;
use Maatify\Product\Variant\Exception\VariantCompositionConflictException;
use Maatify\Product\Variant\Exception\VariantNotFoundException;
use Maatify\Product\Variant\ValueObject\OptionValueSelection;
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

    // Lifecycle, reconfiguration, resolution, and image assignments through the public API.
    $clock = new SystemClock(new DateTimeZone('UTC'));
    $options = ProductFactory::createOptionService($pdo, $clock);
    $values = ProductFactory::createOptionValueService($pdo, $clock);
    $variants = ProductFactory::createVariantService($pdo, $clock);
    $productId = $created->productId;
    $pdo->prepare('INSERT INTO maa_product_product_contents (product_id, language_code, name, created_at, updated_at) VALUES (?, NULL, ?, ?, ?)')
        ->execute([$productId, 'Simple one', '2026-01-01 00:00:00', '2026-01-01 00:00:00']);
    $service->updateStatus($productId, ProductStatusEnum::ACTIVE);
    check($service->getById($productId)->status === ProductStatusEnum::ACTIVE, 'a named Product with a valid configuration can be activated');
    check($variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO())->id === $created->variantId, 'an empty selection resolves the simple Variant');

    $color = $options->create(new CreateOptionCommand($productId, 'color'))->optionId;
    $red = $values->create(new CreateOptionValueCommand($color, 'red'))->optionValueId;
    $blue = $values->create(new CreateOptionValueCommand($color, 'blue'))->optionValueId;
    $pdo->prepare('INSERT INTO maa_product_option_contents (option_id, language_code, name, created_at, updated_at) VALUES (?, NULL, ?, ?, ?)')
        ->execute([$color, 'Color', '2026-01-01 00:00:00', '2026-01-01 00:00:00']);
    $labels = $pdo->prepare('INSERT INTO maa_product_option_value_contents (option_value_id, language_code, name, created_at, updated_at) VALUES (?, NULL, ?, ?, ?)');
    $labels->execute([$red, 'Red', '2026-01-01 00:00:00', '2026-01-01 00:00:00']);
    $labels->execute([$blue, 'Blue', '2026-01-01 00:00:00', '2026-01-01 00:00:00']);
    check($options->getByIdForManagement($color)->status->value === 'inactive', 'a new Option is inactive');

    $result = $service->reconfigure(new ReconfigureProductCommand($productId, [new ReconfigureOptionTarget($color, [$red, $blue])], [
        new ReconfigureVariantTarget([new OptionValueSelection($color, $red)]),
        new ReconfigureVariantTarget([new OptionValueSelection($color, $blue)]),
    ]));
    check(count($result->createdVariantIds) === 2 && $result->deactivatedVariantIds === [$created->variantId], 'reconfiguration creates the new Variants and deactivates the empty one');
    [$redVariant, $blueVariant] = $result->createdVariantIds;
    check($variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO([new OptionValueSelection($color, $blue)]))->id === $blueVariant, 'a complete selection resolves to its Variant');
    try {
        $variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO());
        check(false, 'an incomplete selection must be rejected');
    } catch (ProductInvalidArgumentException) {
        check(true, 'an incomplete selection is a validation failure');
    }
    try {
        $service->reconfigure(new ReconfigureProductCommand($productId, [new ReconfigureOptionTarget($color, [$red])], [new ReconfigureVariantTarget([new OptionValueSelection($color, $red)])]));
        check(false, 'an existing composition without reuseVariantId must conflict');
    } catch (VariantCompositionConflictException) {
        check(true, 'an existing composition is reused only by explicit identity');
    }
    try {
        $variants->softDelete($blueVariant);
        check(false, 'deleting the Variant that represents blue must be rejected');
    } catch (ProductBusinessRuleException) {
        check(true, 'a mutation that would break the configuration is a business-rule failure');
    }
    $back = $service->reconfigure(new ReconfigureProductCommand($productId, [], [new ReconfigureVariantTarget([], $created->variantId)]));
    check($back->reactivatedVariantIds === [$created->variantId] && $back->deactivatedVariantIds === [$redVariant, $blueVariant], 'the transition back to simple reuses the original empty Variant');
    try {
        $variants->resolveByComposition($productId, new VariantCompositionCriteriaDTO([new OptionValueSelection($color, $red)]));
        check(false, 'a retired Option must not be selectable');
    } catch (ProductInvalidArgumentException) {
        check(true, 'a retired Option is not selectable');
    }

    $roles = ProductFactory::createImageRoleService($pdo, $clock);
    $assignments = ProductFactory::createImageAssignmentService($pdo, $clock);
    $hero = $roles->create(new CreateImageRoleCommand('hero'))->roleId;
    $first = $assignments->create(new CreateImageAssignmentCommand($productId, null, 501, $hero))->assignmentId;
    $second = $assignments->create(new CreateImageAssignmentCommand($productId, null, '502', $hero))->assignmentId;
    $assignments->setDefault($first);
    $assignments->setDefault($second);
    check(!$assignments->getByIdForManagement($first)->isDefault && $assignments->getByIdForManagement($second)->isDefault, 'set-default replaces the previous default of the scope');
    $assignments->softDelete($second);
    check(!$assignments->getByIdForManagement($first)->isDefault, 'deleting the default promotes nothing');

    $service->softDelete($productId);
    try {
        $service->updateStatus($productId, ProductStatusEnum::INACTIVE);
        check(false, 'a soft-deleted Product must accept nothing but restore');
    } catch (ProductBusinessRuleException) {
        check(true, 'a soft-deleted Product accepts nothing but restore');
    }
    $service->restore($productId);
    check($service->getById($productId)->status === ProductStatusEnum::ACTIVE, 'restore preserves the saved status');
    try {
        $variants->getById($redVariant);
        check(false, 'an inactive Variant must not be consumer-visible');
    } catch (VariantNotFoundException) {
        check(true, 'an inactive Variant is hidden from consumers');
    }
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "Consumer verification passed.\n";
