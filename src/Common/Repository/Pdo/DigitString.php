<?php

declare(strict_types=1);

namespace Maatify\Product\Common\Repository\Pdo;

/**
 * Recognition of an unsigned decimal digit string, as returned for an identity by
 * `PDO::lastInsertId()` or a fetched integer column.
 *
 * The rule is exactly the digit test of the PHP `ctype` extension on a string, without depending on
 * that extension: a non-empty string of the ASCII digits `0` to `9` only. It accepts `'0'` and
 * leading zeroes, rejects the empty string, signs, whitespace, and decimal points, and does not
 * bound the value, so a digit string above `PHP_INT_MAX` is accepted; the caller checks the range.
 * A `false` (the failure value of `PDO::lastInsertId()`) is not a digit string.
 *
 * @internal Not part of the Public Runtime API.
 */
final class DigitString
{
    private function __construct() {}

    public static function matches(string|false $value): bool
    {
        return $value !== false && preg_match('/\A[0-9]+\z/', $value) === 1;
    }
}
