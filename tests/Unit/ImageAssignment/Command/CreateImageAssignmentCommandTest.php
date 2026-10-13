<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Unit\ImageAssignment\Command;

use Maatify\Product\Common\Exception\ProductInvalidArgumentException;
use Maatify\Product\ImageAssignment\Command\CreateImageAssignmentCommand;
use Maatify\Product\ImageAssignment\ValueObject\ImageAssignmentScope;
use PHPUnit\Framework\TestCase;

final class CreateImageAssignmentCommandTest extends TestCase
{
    public function testMediaAssetIdFollowsTheSharedPositiveIdentityContract(): void
    {
        self::assertSame(7, (new CreateImageAssignmentCommand(1, null, 7))->mediaAssetId);
        self::assertSame(7, (new CreateImageAssignmentCommand(1, null, '7'))->mediaAssetId);
        self::assertSame(PHP_INT_MAX, (new CreateImageAssignmentCommand(1, null, (string) PHP_INT_MAX))->mediaAssetId);

        foreach ([0, -1, '0', '07', '+7', ' 7', '7 ', '7.0', '7e1', '', '9223372036854775808', '99999999999999999999'] as $invalid) {
            try {
                new CreateImageAssignmentCommand(1, null, $invalid);
                self::fail(var_export($invalid, true) . ' must be rejected.');
            } catch (ProductInvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testScopeTextsAreNullOrNonBlankBoundedStrings(): void
    {
        $command = new CreateImageAssignmentCommand(1, 2, 3, 4, 'en-GB', 'web');
        self::assertSame([1, 2, 3, 4, 'en-GB', 'web'], [$command->productId, $command->variantId, $command->mediaAssetId, $command->roleId, $command->languageCode, $command->platform]);
        self::assertNull((new CreateImageAssignmentCommand(1, null, 3))->languageCode);

        foreach ([
            static fn() => new CreateImageAssignmentCommand(0, null, 1),
            static fn() => new CreateImageAssignmentCommand(1, 0, 1),
            static fn() => new CreateImageAssignmentCommand(1, null, 1, 0),
            static fn() => new CreateImageAssignmentCommand(1, null, 1, null, ''),
            static fn() => new CreateImageAssignmentCommand(1, null, 1, null, " \t"),
            static fn() => new CreateImageAssignmentCommand(1, null, 1, null, str_repeat('a', 17)),
            static fn() => new CreateImageAssignmentCommand(1, null, 1, null, null, ''),
            static fn() => new CreateImageAssignmentCommand(1, null, 1, null, null, str_repeat('p', 256)),
            static fn() => new CreateImageAssignmentCommand(1, null, 1, null, "\xff"),
        ] as $call) {
            try {
                $call();
                self::fail('The input must be rejected.');
            } catch (ProductInvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheScopeKeyMatchesTheGeneratedColumnEncoding(): void
    {
        self::assertSame('P5|N:|N:|N:|N:', ImageAssignmentScope::of(5, null, null, null, null)->value);
        self::assertSame('P5|V9|L2:en|L3:web|R4', ImageAssignmentScope::of(5, 9, 4, 'en', 'web')->value);
        self::assertSame('P5|N:|L2:ñé|N:|N:', ImageAssignmentScope::of(5, null, null, 'ñé', null)->value, 'Length counts characters, as CHAR_LENGTH does.');
    }
}
