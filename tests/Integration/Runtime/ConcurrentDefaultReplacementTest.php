<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Runtime;

use Maatify\Product\ImageAssignment\Management\Command\CreateImageAssignmentCommand;

/**
 * Real concurrent default replacements within one exact scope: separate processes and connections
 * released at once. The target-scope lock and the database unique key leave exactly one default.
 */
final class ConcurrentDefaultReplacementTest extends RuntimeTestCase
{
    use ConcurrentWorkers;

    private const int WORKERS = 8;

    public function testConcurrentSetDefaultWithinOneScopeLeavesExactlyOneDefault(): void
    {
        $created = $this->namedProduct('DEFAULTS');
        $service = $this->imageAssignmentManagementService();
        $commands = [];
        for ($i = 1; $i <= self::WORKERS; ++$i) {
            $assignmentId = $service->create(new CreateImageAssignmentCommand($created->productId, null, $i))->assignmentId;
            $commands[] = ['set-default', ['assignmentId' => $assignmentId]];
        }

        $outcomes = $this->runCommandWorkers($commands);

        foreach ($outcomes as $outcome) {
            self::assertStringStartsWith('ok:', $outcome, implode(' | ', $outcomes));
        }
        self::assertSame(1, self::rowCount('maa_product_image_assignments', 'is_default = 1'), implode(' | ', $outcomes));
        self::assertSame(self::WORKERS, self::rowCount('maa_product_image_assignments', 'deleted_at IS NULL'));
    }
}
