<?php

declare(strict_types=1);

namespace Maatify\Product\Tests\Unit\Variant;

use Maatify\Product\Variant\ValueObject\CompositionFingerprint;
use PHPUnit\Framework\TestCase;

final class CompositionFingerprintTest extends TestCase
{
    public function testEmptyCompositionIsTheSha256OfTheCanonicalString(): void
    {
        // Known SHA-256 vector of the UTF-8 string "v1" (architecture section 5.3).
        self::assertSame(
            '3bfc269594ef649228e9a74bab00f042efc91d5acc6fbee31a382e80d42388fe',
            CompositionFingerprint::forEmptyComposition()->value,
        );
    }

    public function testIsLowercaseSixtyFourCharacterHex(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', CompositionFingerprint::forEmptyComposition()->value);
    }

    public function testIsDeterministic(): void
    {
        self::assertSame(
            CompositionFingerprint::forEmptyComposition()->value,
            CompositionFingerprint::forEmptyComposition()->value,
        );
    }
}
