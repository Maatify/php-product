<?php

declare(strict_types=1);

namespace Maatify\Product\ImageAssignment\ValueObject;

/**
 * The exact `(target, language, platform, role)` scope key of an Image Assignment, in the same
 * encoding as the generated `ordering_scope` column of `maa_product_image_assignments`, so the
 * shared Ordering API and the default-replacement write address one scope column.
 *
 * Encoding: `P{product}|{V{variant}|N:}|{L{len}:{language}|N:}|{L{len}:{platform}|N:}|{R{role}|N:}`,
 * where `len` counts characters. Integration tests assert that it equals the stored generated value.
 *
 * @internal Not part of the Public Runtime API.
 */
final readonly class ImageAssignmentScope
{
    /**
     * @param non-empty-string $value
     */
    private function __construct(public string $value) {}

    public static function of(int $productId, ?int $variantId, ?int $roleId, ?string $languageCode, ?string $platform): self
    {
        return new self(
            'P' . $productId . '|'
            . ($variantId === null ? 'N:' : 'V' . $variantId) . '|'
            . self::text($languageCode) . '|'
            . self::text($platform) . '|'
            . ($roleId === null ? 'N:' : 'R' . $roleId),
        );
    }

    private static function text(?string $value): string
    {
        return $value === null ? 'N:' : 'L' . mb_strlen($value, 'UTF-8') . ':' . $value;
    }
}
