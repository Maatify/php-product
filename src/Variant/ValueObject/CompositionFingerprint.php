<?php

declare(strict_types=1);

namespace Maatify\Product\Variant\ValueObject;

/**
 * Canonical composition fingerprint of a Variant, as defined by the V1 architecture.
 *
 * The fingerprint is the lowercase hexadecimal SHA-256 digest of the canonical
 * composition string: `v1` for the empty composition, followed by one
 * `|{option_id}={option_value_id}` segment per pair. Only the empty composition is
 * available in this release.
 */
final readonly class CompositionFingerprint
{
    /** Canonical string of the empty composition. */
    private const string EMPTY_CANONICAL = 'v1';

    /**
     * @param non-empty-string $value Lowercase 64-character hexadecimal digest.
     */
    private function __construct(public string $value) {}

    /**
     * The fingerprint of a Variant with no Option Values, the identity of a simple Product's Variant.
     */
    public static function forEmptyComposition(): self
    {
        return new self(hash('sha256', self::EMPTY_CANONICAL));
    }
}
