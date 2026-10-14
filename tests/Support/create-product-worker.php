<?php

/**
 * Child process for the concurrency test: waits for a shared start instant, then
 * creates one Product through the public factory and prints the outcome.
 *
 * Usage: php create-product-worker.php <code> <start-at-microtime>
 */

declare(strict_types=1);

use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\Product\Exception\ProductCodeAlreadyExistsException;
use Maatify\Product\Product\Management\Command\CreateProductCommand;
use Maatify\SharedCommon\Infrastructure\SystemClock;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/** @var list<string> $argv */
$code = $argv[1] ?? '';
$startAt = (float) ($argv[2] ?? '0');

$env = static function (string $name): string {
    $value = getenv($name);

    return is_string($value) ? $value : '';
};

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env('PRODUCT_TEST_DB_HOST'), $env('PRODUCT_TEST_DB_PORT'), $env('PRODUCT_TEST_DB_NAME')),
    $env('PRODUCT_TEST_DB_USER'),
    $env('PRODUCT_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
);
$service = ProductFactory::createProductManagementService($pdo, new SystemClock(new DateTimeZone('UTC')));

while (microtime(true) < $startAt) {
    usleep(200);
}

try {
    $created = $service->create(new CreateProductCommand($code));
    echo 'created:' . $created->productId . ':' . $created->variantId;
} catch (ProductCodeAlreadyExistsException) {
    echo 'conflict';
} catch (Throwable $e) {
    echo 'error:' . $e::class . ':' . $e->getMessage();
}
