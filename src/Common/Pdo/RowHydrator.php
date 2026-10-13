<?php

declare(strict_types=1);

namespace Maatify\Product\Common\Pdo;

use DateTimeImmutable;
use Exception;
use Maatify\Product\Common\Exception\ProductPersistenceException;
use Maatify\Product\Common\Exception\ProductStorageFailureFactory;
use Maatify\SharedCommon\Contracts\ClockInterface;

/**
 * Typed access to a fetched row for the package's PDO read adapters.
 *
 * Internal: not part of the Public Runtime API. A value the schema contract does not allow
 * raises {@see ProductPersistenceException}.
 *
 * @internal
 */
final readonly class RowHydrator
{
    public function __construct(private ClockInterface $clock) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw ProductStorageFailureFactory::unexpectedColumnType($column);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value !== null && !is_string($value)) {
            throw ProductStorageFailureFactory::unexpectedColumnType($column);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return positive-int
     */
    public static function positiveInt(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw ProductStorageFailureFactory::unexpectedColumnType($column);
        }
        if (!ctype_digit((string) $value) || (int) $value < 1) {
            throw ProductStorageFailureFactory::invalidStorageValue($column);
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return positive-int|null
     */
    public static function nullablePositiveInt(array $row, string $column): ?int
    {
        return ($row[$column] ?? null) === null ? null : self::positiveInt($row, $column);
    }

    /**
     * Reads a `0`/`1` flag column (a `TINYINT(1)` or a boolean SQL expression).
     *
     * @param array<string, mixed> $row
     */
    public static function flag(array $row, string $column): bool
    {
        $value = $row[$column] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (!is_int($value) && !is_string($value)) {
            throw ProductStorageFailureFactory::unexpectedColumnType($column);
        }
        $digits = (string) $value;
        if ($digits !== '0' && $digits !== '1') {
            throw ProductStorageFailureFactory::invalidStorageValue($column);
        }

        return $digits === '1';
    }

    /**
     * Reads a status column and reports whether it is exactly `active`; any other stored value
     * than `active` or `inactive` breaks the schema contract.
     *
     * @param array<string, mixed> $row
     */
    public static function isActive(array $row, string $column = 'status'): bool
    {
        $status = self::string($row, $column);
        if ($status !== 'active' && $status !== 'inactive') {
            throw ProductStorageFailureFactory::invalidStorageValue($column);
        }

        return $status === 'active';
    }

    /**
     * @param array<string, mixed> $row
     */
    public function timestamp(array $row, string $column): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable(self::string($row, $column), $this->clock->getTimezone());
        } catch (Exception) {
            throw ProductStorageFailureFactory::invalidStorageValue($column);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function nullableTimestamp(array $row, string $column): ?DateTimeImmutable
    {
        return ($row[$column] ?? null) === null ? null : $this->timestamp($row, $column);
    }
}
