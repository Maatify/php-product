<?php

declare(strict_types=1);

/**
 * Consumer Verification Harness for maatify/php-product.
 *
 * Runs in an external Composer root that installed the package as a dependency.
 * It uses only the installed package's Composer-delivered assets (never the
 * package repository's sources or test namespaces) and a real MySQL service.
 *
 * The package currently ships no PHP runtime classes, so its observable public
 * contract is the installed schema asset; production PSR-4 autoload and a PHP
 * public API cannot be exercised yet (see docs/verification/CI_GATES.md).
 */

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

/** @param list<int|string|null> $params */
function scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

function rejected(PDO $pdo, int $errno, string $sql, array $params): bool
{
    try {
        $pdo->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        return ($e->errorInfo[1] ?? null) === $errno;
    }

    return false;
}

check(Composer\InstalledVersions::isInstalled('maatify/php-product'), 'package is installed through Composer');
$installPath = Composer\InstalledVersions::getInstallPath('maatify/php-product');
check(is_string($installPath) && is_dir($installPath), 'installed package path exists');
check(!is_link((string) $installPath), 'package was copied, not symlinked from the repository');

$schemaFile = $installPath . '/schema/product.sql';
check(is_file($schemaFile), 'schema/product.sql is delivered with the installed package');
$sql = file_get_contents($schemaFile);
check(is_string($sql) && $sql !== '', 'installed schema is readable');

$statements = [];
foreach (explode(";\n", (string) preg_replace('/^--.*$/m', '', (string) $sql)) as $statement) {
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
    check(str_starts_with((string) scalar($pdo, 'SELECT VERSION()'), env('PRODUCT_TEST_EXPECTED_VERSION')), 'connected to the expected MySQL version');

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    check((int) scalar($pdo, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'maa\\_product\\_%'") === 10, 'installed schema creates the ten Product tables');

    // Realistic workflow: a simple Product, a configurable Product, and media.
    $ts = '2026-01-01 00:00:00';
    $pdo->prepare('INSERT INTO maa_product_products (code, status, created_at, updated_at) VALUES (?, ?, ?, ?)')->execute(['SIMPLE-1', 'inactive', $ts, $ts]);
    $simple = (int) $pdo->lastInsertId();
    $empty = hash('sha256', 'v1');
    $pdo->prepare('INSERT INTO maa_product_variants (product_id, composition_fingerprint, status, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$simple, $empty, 'active', 1, $ts, $ts]);
    $simpleVariant = (int) $pdo->lastInsertId();
    check($simpleVariant > 0, 'simple Product receives its empty-composition Variant');
    check(rejected($pdo, 1062, 'INSERT INTO maa_product_variants (product_id, composition_fingerprint, status, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$simple, $empty, 'active', 2, $ts, $ts]), 'a duplicate composition is rejected');

    $pdo->prepare('INSERT INTO maa_product_products (code, status, created_at, updated_at) VALUES (?, ?, ?, ?)')->execute(['CONF-1', 'inactive', $ts, $ts]);
    $configurable = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO maa_product_options (product_id, code, status, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$configurable, 'color', 'active', 1, $ts, $ts]);
    $option = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO maa_product_option_values (option_id, code, status, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$option, 'red', 'active', 1, $ts, $ts]);
    $value = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO maa_product_variants (product_id, composition_fingerprint, status, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$configurable, hash('sha256', "v1|{$option}={$value}"), 'active', 1, $ts, $ts]);
    $variant = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO maa_product_variant_option_values (product_id, variant_id, option_id, option_value_id) VALUES (?, ?, ?, ?)')->execute([$configurable, $variant, $option, $value]);
    check((int) scalar($pdo, 'SELECT option_value_id FROM maa_product_variant_option_values WHERE variant_id = ?', [$variant]) === $value, 'configurable Variant composition is stored and readable');
    check(rejected($pdo, 1452, 'INSERT INTO maa_product_variant_option_values (product_id, variant_id, option_id, option_value_id) VALUES (?, ?, ?, ?)', [$simple, $simpleVariant, $option, $value]), 'cross-Product composition is rejected by the database');

    $pdo->prepare('INSERT INTO maa_product_media_assignments (product_id, variant_id, media_asset_id, is_primary, display_order, created_at, updated_at) VALUES (?, NULL, ?, 1, 1, ?, ?)')->execute([$configurable, 4242, $ts, $ts]);
    check(rejected($pdo, 1062, 'INSERT INTO maa_product_media_assignments (product_id, variant_id, media_asset_id, is_primary, display_order, created_at, updated_at) VALUES (?, NULL, ?, 1, 2, ?, ?)', [$configurable, 4343, $ts, $ts]), 'a second primary in the same exact scope is rejected');
    check(rejected($pdo, 3819, 'INSERT INTO maa_product_products (code, created_at, updated_at) VALUES (?, ?, ?)', ["\t", $ts, $ts]), 'a whitespace-only code is rejected');
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "Consumer verification passed.\n";
