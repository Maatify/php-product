<?php

declare(strict_types=1);

/**
 * Child process for the concurrency tests: waits for a shared start instant, then runs one public
 * command through the factory on its own connection and prints the outcome.
 *
 * Usage: php command-worker.php <start-at-microtime> <operation> <json-arguments>
 *   reconfigure  {"productId": int, "options": {optionId: [valueId, ...]}, "variants": [[[optionId, valueId], ...], reuseVariantId|null]}
 *   set-default  {"assignmentId": int}
 *   restore-as-default  {"assignmentId": int}
 */

use Maatify\Product\Factory\ProductFactory;
use Maatify\Product\ImageAssignment\Exception\ImageAssignmentDefaultConflictException;
use Maatify\Product\Product\Management\Command\ReconfigureOptionTarget;
use Maatify\Product\Product\Management\Command\ReconfigureProductCommand;
use Maatify\Product\Product\Management\Command\ReconfigureVariantTarget;
use Maatify\Product\Variant\Exception\VariantCompositionConflictException;
use Maatify\Product\Variant\ValueObject\OptionValueSelection;
use Maatify\SharedCommon\Infrastructure\SystemClock;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/** @var list<string> $argv */
$startAt = (float) ($argv[1] ?? '0');
$operation = $argv[2] ?? '';
/** @var array<string, mixed> $arguments */
$arguments = json_decode($argv[3] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

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
$clock = new SystemClock(new DateTimeZone('UTC'));

while (microtime(true) < $startAt) {
    usleep(200);
}

try {
    switch ($operation) {
        case 'reconfigure':
            $options = [];
            /** @var array<int|string, list<int>> $optionSpecs */
            $optionSpecs = $arguments['options'] ?? [];
            foreach ($optionSpecs as $optionId => $valueIds) {
                $options[] = new ReconfigureOptionTarget((int) $optionId, $valueIds);
            }
            $variants = [];
            /** @var list<array{0: list<array{int, int}>, 1?: int|null}> $variantSpecs */
            $variantSpecs = $arguments['variants'] ?? [];
            foreach ($variantSpecs as $spec) {
                $variants[] = new ReconfigureVariantTarget(
                    array_map(static fn(array $pair): OptionValueSelection => new OptionValueSelection($pair[0], $pair[1]), $spec[0]),
                    $spec[1] ?? null,
                );
            }
            /** @var int $productId */
            $productId = $arguments['productId'];
            $result = ProductFactory::createProductManagementService($pdo, $clock)->reconfigure(new ReconfigureProductCommand($productId, $options, $variants));
            echo 'ok:' . implode(',', $result->createdVariantIds);
            break;
        case 'set-default':
            /** @var int $assignmentId */
            $assignmentId = $arguments['assignmentId'];
            ProductFactory::createImageAssignmentManagementService($pdo, $clock)->setDefault($assignmentId);
            echo 'ok:' . $assignmentId;
            break;
        case 'restore-as-default':
            /** @var int $assignmentId */
            $assignmentId = $arguments['assignmentId'];
            ProductFactory::createImageAssignmentManagementService($pdo, $clock)->restoreAsDefault($assignmentId);
            echo 'ok:' . $assignmentId;
            break;
        default:
            echo 'error:unknown operation';
            break;
    }
} catch (VariantCompositionConflictException|ImageAssignmentDefaultConflictException) {
    echo 'conflict';
} catch (Throwable $e) {
    echo 'error:' . $e::class . ':' . $e->getMessage();
}
