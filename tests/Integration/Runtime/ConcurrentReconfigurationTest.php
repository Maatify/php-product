<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Runtime;

use Maatify\Persistence\Pdo\Ordering\ScopedOrderingManager;
use Maatify\Product\Product\Repository\Pdo\PdoProductConfigurationRepository;
use Maatify\Product\Product\Service\ProductConfigurationValidator;

/**
 * Real concurrent reconfigurations of one Product: separate processes and connections released at
 * once. The Product row lock serializes them, so the duplicate-composition rule is applied to the
 * committed state of the previous writer (a typed conflict, never a duplicate-key error) and the
 * final configuration is valid.
 */
final class ConcurrentReconfigurationTest extends RuntimeTestCase
{
    use ConcurrentWorkers;

    private const int WORKERS = 8;

    public function testConcurrentIdenticalReconfigurationsYieldOneWinnerAndTypedConflicts(): void
    {
        $created = $this->namedProduct('RACE');
        $color = $this->preparedOption($created->productId, 'color', 'Color');
        $red = $this->preparedValue($color, 'red', 'Red');
        $blue = $this->preparedValue($color, 'blue', 'Blue');
        $arguments = [
            'productId' => $created->productId,
            'options' => [$color => [$red, $blue]],
            'variants' => [[[[$color, $red]]], [[[$color, $blue]]]],
        ];

        $outcomes = $this->runCommandWorkers(array_fill(0, self::WORKERS, ['reconfigure', $arguments]));

        $created = array_values(array_filter($outcomes, static fn(string $o): bool => str_starts_with($o, 'ok:')));
        $conflicts = array_filter($outcomes, static fn(string $o): bool => $o === 'conflict');
        self::assertCount(1, $created, implode(' | ', $outcomes));
        self::assertCount(self::WORKERS - 1, $conflicts, implode(' | ', $outcomes));
        self::assertSame(3, self::rowCount('maa_product_variants'), 'The empty Variant plus exactly one Variant per composition.');
        self::assertSame(2, self::rowCount('maa_product_variants', "status = 'active'"));
        $this->assertValidConfiguration($arguments['productId']);
    }

    public function testConcurrentDifferentTargetsLeaveOneValidConfiguration(): void
    {
        $created = $this->namedProduct('RACE-2');
        $color = $this->preparedOption($created->productId, 'color', 'Color');
        $red = $this->preparedValue($color, 'red', 'Red');
        $blue = $this->preparedValue($color, 'blue', 'Blue');
        $green = $this->preparedValue($color, 'green', 'Green');
        $commands = [];
        for ($i = 0; $i < self::WORKERS; ++$i) {
            $value = [$red, $blue, $green][$i % 3];
            $commands[] = ['reconfigure', ['productId' => $created->productId, 'options' => [$color => [$value]], 'variants' => [[[[$color, $value]]]]]];
        }

        $outcomes = $this->runCommandWorkers($commands);

        foreach ($outcomes as $outcome) {
            self::assertTrue(str_starts_with($outcome, 'ok:') || $outcome === 'conflict', implode(' | ', $outcomes));
        }
        self::assertGreaterThanOrEqual(1, count(array_filter($outcomes, static fn(string $o): bool => str_starts_with($o, 'ok:'))));
        self::assertSame(1, self::rowCount('maa_product_variants', "status = 'active' AND deleted_at IS NULL"), 'Exactly one target set is the committed configuration.');
        self::assertSame(1, self::rowCount('maa_product_option_values', "status = 'active'"));
        self::assertLessThanOrEqual(4, self::rowCount('maa_product_variants'), 'At most one identity per composition exists.');
        $this->assertValidConfiguration($created->productId);
    }

    private function assertValidConfiguration(int $productId): void
    {
        self::$pdo->beginTransaction();
        try {
            $snapshot = (new PdoProductConfigurationRepository(self::$pdo, new ScopedOrderingManager()))->lockSnapshot($productId);
            self::assertNotNull($snapshot);
            self::assertNull(ProductConfigurationValidator::firstViolation($snapshot));
        } finally {
            self::$pdo->rollBack();
        }
    }
}
