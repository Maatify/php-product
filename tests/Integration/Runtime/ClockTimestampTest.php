<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Integration\Runtime;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\Product\Common\Enum\ProductDeletedStateEnum;
use Maatify\Product\Content\Management\Command\CreateProductContentCommand;
use Maatify\Product\ImageAssignment\Management\Command\CreateImageAssignmentCommand;
use Maatify\Product\Option\Management\Command\CreateOptionCommand;
use Maatify\Product\Product\Enum\ProductStatusEnum;
use Maatify\Product\Product\Management\Command\CreateProductCommand;
use Maatify\SharedCommon\Contracts\ClockInterface;

/**
 * Timestamps against real MySQL through the public services: `created_at` and `deleted_at` are the
 * Host Clock's instant stored as its own `Y-m-d H:i:s` wall-clock string, and every DTO timestamp is
 * hydrated in the timezone of the Clock the reading service was built with, with no normalization.
 * The Clock runs in `Asia/Tokyo` (UTC+9), so any conversion to UTC or to the server's zone would
 * store or return a different string.
 */
final class ClockTimestampTest extends RuntimeTestCase
{
    private const string TOKYO = 'Asia/Tokyo';
    private const string CREATED = '2031-02-03 04:05:06';
    private const string DELETED = '2032-03-04 05:06:07';

    public function testProductAndVariantTimestampsComeFromTheClockAndHydrateInItsTimezone(): void
    {
        $products = $this->productManagementService(self::createClock());
        $created = $products->create(new CreateProductCommand('P-TOKYO'));

        self::assertSame([self::CREATED, self::CREATED, null], self::storedTimestamps('maa_product_products', $created->productId));
        self::assertSame([self::CREATED, self::CREATED, null], self::storedTimestamps('maa_product_variants', $created->variantId));

        $product = $products->getByIdForManagement($created->productId);
        self::assertInClockTime(self::CREATED, $product->createdAt, self::TOKYO);
        self::assertInClockTime(self::CREATED, $product->updatedAt, self::TOKYO);
        self::assertInClockTime(self::CREATED, $this->variantManagementService(self::createClock())->getByIdForManagement($created->variantId)->createdAt, self::TOKYO);

        $this->nameProduct($created->productId, 'Tokyo');
        $products->updateStatus($created->productId, ProductStatusEnum::ACTIVE);
        self::assertInClockTime(self::CREATED, $this->productConsumerService(self::createClock())->getById($created->productId)->createdAt, self::TOKYO);
        self::assertInClockTime(self::CREATED, $this->variantConsumerService(self::createClock())->getById($created->variantId)->createdAt, self::TOKYO);

        $this->productManagementService(self::deleteClock())->softDelete($created->productId);
        self::assertSame([self::CREATED, self::DELETED, self::DELETED], self::storedTimestamps('maa_product_products', $created->productId));
        $deleted = $products->getByIdForManagement($created->productId, ProductDeletedStateEnum::DELETED_ONLY);
        self::assertNotNull($deleted->deletedAt);
        self::assertInClockTime(self::DELETED, $deleted->deletedAt, self::TOKYO);

        $elsewhere = $this->productManagementService(self::fixedClock(self::CREATED, 'America/New_York'))
            ->getByIdForManagement($created->productId, ProductDeletedStateEnum::DELETED_ONLY);
        self::assertInClockTime(self::CREATED, $elsewhere->createdAt, 'America/New_York');
    }

    public function testOptionTimestampsComeFromTheClockAndHydrateInItsTimezone(): void
    {
        $productId = $this->productManagementService()->create(new CreateProductCommand('P-OPTION'))->productId;
        $optionId = $this->optionManagementService(self::createClock())->create(new CreateOptionCommand($productId, 'color'))->optionId;

        self::assertSame([self::CREATED, self::CREATED, null], self::storedTimestamps('maa_product_options', $optionId));
        self::assertInClockTime(self::CREATED, $this->optionManagementService(self::createClock())->getByIdForManagement($optionId)->createdAt, self::TOKYO);

        $this->optionManagementService(self::deleteClock())->softDelete($optionId);
        self::assertSame([self::CREATED, self::DELETED, self::DELETED], self::storedTimestamps('maa_product_options', $optionId));
        $deletedAt = $this->optionManagementService(self::createClock())->getByIdForManagement($optionId, ProductDeletedStateEnum::DELETED_ONLY)->deletedAt;
        self::assertNotNull($deletedAt);
        self::assertInClockTime(self::DELETED, $deletedAt, self::TOKYO);
    }

    public function testImageAssignmentTimestampsComeFromTheClockAndHydrateInItsTimezone(): void
    {
        $productId = $this->productManagementService()->create(new CreateProductCommand('P-IMAGE'))->productId;
        $assignmentId = $this->imageAssignmentManagementService(self::createClock())->create(new CreateImageAssignmentCommand($productId, null, 1))->assignmentId;

        self::assertSame([self::CREATED, self::CREATED, null], self::storedTimestamps('maa_product_image_assignments', $assignmentId));
        self::assertInClockTime(self::CREATED, $this->imageAssignmentManagementService(self::createClock())->getByIdForManagement($assignmentId)->createdAt, self::TOKYO);

        $this->imageAssignmentManagementService(self::deleteClock())->softDelete($assignmentId);
        self::assertSame([self::CREATED, self::DELETED, self::DELETED], self::storedTimestamps('maa_product_image_assignments', $assignmentId));
        $deletedAt = $this->imageAssignmentManagementService(self::createClock())->getByIdForManagement($assignmentId, ProductDeletedStateEnum::DELETED_ONLY)->deletedAt;
        self::assertNotNull($deletedAt);
        self::assertInClockTime(self::DELETED, $deletedAt, self::TOKYO);
    }

    public function testContentTimestampsComeFromTheClockAndHydrateInItsTimezone(): void
    {
        $productId = $this->productManagementService()->create(new CreateProductCommand('P-CONTENT'))->productId;
        $contentId = $this->productContentManagementService(self::createClock())->create(new CreateProductContentCommand($productId, 'ja', 'Name'))->contentId;

        self::assertSame([self::CREATED, self::CREATED, null], self::storedTimestamps('maa_product_product_contents', $contentId));
        self::assertInClockTime(self::CREATED, $this->productContentManagementService(self::createClock())->getByIdForManagement($contentId)->createdAt, self::TOKYO);

        $this->productContentManagementService(self::deleteClock())->softDelete($contentId);
        self::assertSame([self::CREATED, self::DELETED, self::DELETED], self::storedTimestamps('maa_product_product_contents', $contentId));
        $deletedAt = $this->productContentManagementService(self::createClock())->getByIdForManagement($contentId, ProductDeletedStateEnum::DELETED_ONLY)->deletedAt;
        self::assertNotNull($deletedAt);
        self::assertInClockTime(self::DELETED, $deletedAt, self::TOKYO);
    }

    private static function createClock(): ClockInterface
    {
        return self::fixedClock(self::CREATED, self::TOKYO);
    }

    private static function deleteClock(): ClockInterface
    {
        return self::fixedClock(self::DELETED, self::TOKYO);
    }

    /**
     * Asserts the DTO value is the stored wall-clock string in the reading Clock's timezone, the same
     * instant as that string read in that timezone.
     */
    private static function assertInClockTime(string $expected, DateTimeImmutable $actual, string $timezone): void
    {
        self::assertSame($timezone, $actual->getTimezone()->getName(), 'Hydrated in the Host Clock timezone.');
        self::assertSame($expected, $actual->format('Y-m-d H:i:s'), 'No normalization of the stored wall-clock value.');
        self::assertSame((new DateTimeImmutable($expected, new DateTimeZone($timezone)))->getTimestamp(), $actual->getTimestamp());
    }

    /** @return array{string|null, string|null, string|null} created_at, updated_at, deleted_at as stored */
    private static function storedTimestamps(string $table, int $id): array
    {
        $statement = self::$pdo->prepare(
            'SELECT CAST(created_at AS CHAR), CAST(updated_at AS CHAR), CAST(deleted_at AS CHAR) FROM `' . $table . '` WHERE id = :i',
        );
        $statement->execute(['i' => $id]);
        $row = $statement->fetch();
        self::assertIsArray($row);
        $values = array_values($row);
        foreach ($values as $value) {
            self::assertTrue($value === null || is_string($value));
        }

        /** @var array{string|null, string|null, string|null} */
        return [$values[0], $values[1], $values[2]];
    }
}
