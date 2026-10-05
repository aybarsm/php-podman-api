<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Internal\Support;

use Aybarsm\Podman\Api\Exceptions\HydrationException;
use DateTimeImmutable;
use Exception;

/**
 * Typed readers used by DTO::fromArray(). Missing keys and JSON null are equivalent (Go's omitempty / nil slices).
 *
 * @internal
 */
final class Data
{
    private const string GO_ZERO_TIME = '0001-01-01T00:00:00';

    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        return self::stringOrNull($data, $key) ?? throw HydrationException::missingKey($key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function stringOrNull(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;

        return match (true) {
            $v === null => null,
            is_string($v) => $v,
            default => throw HydrationException::unexpectedType($key, 'string', $v),
        };
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        return self::intOrNull($data, $key) ?? throw HydrationException::missingKey($key);
    }

    /**
     * Go uint64 values beyond PHP_INT_MAX (e.g. "unlimited" sentinels) are clamped to PHP_INT_MAX.
     *
     * @param array<array-key, mixed> $data
     */
    public static function intOrNull(array $data, string $key): ?int
    {
        $v = $data[$key] ?? null;

        return match (true) {
            $v === null => null,
            is_int($v) => $v,
            is_float($v) && $v >= (float) PHP_INT_MAX => PHP_INT_MAX,
            is_float($v) && floor($v) === $v => (int) $v,
            default => throw HydrationException::unexpectedType($key, 'int', $v),
        };
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function float(array $data, string $key): float
    {
        return self::floatOrNull($data, $key) ?? throw HydrationException::missingKey($key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function floatOrNull(array $data, string $key): ?float
    {
        $v = $data[$key] ?? null;

        return match (true) {
            $v === null => null,
            is_int($v), is_float($v) => (float) $v,
            default => throw HydrationException::unexpectedType($key, 'float', $v),
        };
    }

    /**
     * Booleans default to false when omitted: Go drops `false` under omitempty.
     *
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        return self::boolOrNull($data, $key) ?? false;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function boolOrNull(array $data, string $key): ?bool
    {
        $v = $data[$key] ?? null;

        return match (true) {
            $v === null => null,
            is_bool($v) => $v,
            default => throw HydrationException::unexpectedType($key, 'bool', $v),
        };
    }

    /**
     * Accepts RFC 3339 strings (nanoseconds are truncated to microseconds) and unix timestamps.
     * Go's zero time (0001-01-01T00:00:00Z, "never happened") reads as null.
     *
     * @param array<array-key, mixed> $data
     */
    public static function dateTimeOrNull(array $data, string $key): ?DateTimeImmutable
    {
        $v = $data[$key] ?? null;

        if ($v === null || $v === '' || (is_string($v) && str_starts_with($v, self::GO_ZERO_TIME))) {
            return null;
        }
        if (is_int($v)) {
            return new DateTimeImmutable('@'.$v);
        }
        if (! is_string($v)) {
            throw HydrationException::unexpectedType($key, 'date-time', $v);
        }

        $normalised = preg_replace('/(\.\d{6})\d+/', '$1', $v) ?? $v;
        try {
            return new DateTimeImmutable($normalised);
        } catch (Exception) {
            throw HydrationException::unexpectedType($key, 'RFC 3339 date-time', $v);
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function dateTime(array $data, string $key): DateTimeImmutable
    {
        return self::dateTimeOrNull($data, $key) ?? throw HydrationException::missingKey($key);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function stringList(array $data, string $key): array
    {
        return array_map(
            static fn (mixed $v): string => is_string($v) ? $v : throw HydrationException::unexpectedType($key.'[]', 'string', $v),
            self::list($data, $key),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<int>
     */
    public static function intList(array $data, string $key): array
    {
        return array_map(
            static fn (mixed $v): int => self::intOrNull(['v' => $v], 'v') ?? throw HydrationException::unexpectedType($key.'[]', 'int', $v),
            self::list($data, $key),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, string>
     */
    public static function stringMap(array $data, string $key): array
    {
        $out = [];
        foreach (self::map($data, $key) as $k => $v) {
            $out[$k] = is_string($v) ? $v : throw HydrationException::unexpectedType("{$key}.{$k}", 'string', $v);
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, list<string>>
     */
    public static function stringListMap(array $data, string $key): array
    {
        $out = [];
        foreach (self::map($data, $key) as $k => $v) {
            $out[$k] = self::stringList([$k => $v], $k);
        }

        return $out;
    }

    /**
     * Go `error` fields: a string when Podman stringifies them, `{}` when it marshals the error struct as-is.
     *
     * @param array<array-key, mixed> $data
     */
    public static function errorOrNull(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;

        return match (true) {
            $v === null, $v === '' => null,
            is_string($v) => $v,
            is_array($v) && is_string($v['message'] ?? null) => $v['message'],
            is_array($v) => 'unspecified error (not serialised by Podman)',
            default => throw HydrationException::unexpectedType($key, 'error string', $v),
        };
    }

    /**
     * Go `[]error`: string or `{}` elements; null elements are dropped.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function errorList(array $data, string $key): array
    {
        $out = [];
        foreach (self::list($data, $key) as $i => $error) {
            $message = self::errorOrNull(["{$key}[{$i}]" => $error], "{$key}[{$i}]");
            if ($message !== null) {
                $out[] = $message;
            }
        }

        return $out;
    }

    /**
     * Go `map[string]error`: each value is null (success), a string or `{}`.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, string|null>
     */
    public static function errorMap(array $data, string $key): array
    {
        $out = [];
        foreach (self::map($data, $key) as $k => $error) {
            $out[$k] = self::errorOrNull([$k => $error], $k);
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    public static function list(array $data, string $key): array
    {
        $v = $data[$key] ?? null;

        return match (true) {
            $v === null => [],
            is_array($v) && array_is_list($v) => $v,
            default => throw HydrationException::unexpectedType($key, 'list', $v),
        };
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $v = $data[$key] ?? null;

        if ($v === null) {
            return [];
        }
        if (! is_array($v)) {
            throw HydrationException::unexpectedType($key, 'object', $v);
        }

        $out = [];
        foreach ($v as $k => $item) {
            $out[(string) $k] = $item;
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function raw(array $data, string $key): mixed
    {
        return $data[$key] ?? null;
    }

    /**
     * @template T
     *
     * @param array<array-key, mixed>              $data
     * @param callable(array<string, mixed>): T    $factory e.g. ContainerState::fromArray(...)
     *
     * @return T
     */
    public static function object(array $data, string $key, callable $factory): mixed
    {
        return self::objectOrNull($data, $key, $factory) ?? throw HydrationException::missingKey($key);
    }

    /**
     * @template T
     *
     * @param array<array-key, mixed>              $data
     * @param callable(array<string, mixed>): T    $factory
     *
     * @return T|null
     */
    public static function objectOrNull(array $data, string $key, callable $factory): mixed
    {
        return ($data[$key] ?? null) === null ? null : $factory(self::map($data, $key));
    }

    /**
     * @template T
     *
     * @param array<array-key, mixed>              $data
     * @param callable(array<string, mixed>): T    $factory
     *
     * @return list<T>
     */
    public static function objectList(array $data, string $key, callable $factory): array
    {
        $out = [];
        foreach (self::list($data, $key) as $i => $item) {
            $out[] = $factory(self::map([$key."[{$i}]" => $item], $key."[{$i}]"));
        }

        return $out;
    }

    /**
     * @template T
     *
     * @param array<array-key, mixed>              $data
     * @param callable(array<string, mixed>): T    $factory
     *
     * @return array<string, T>
     */
    public static function objectMap(array $data, string $key, callable $factory): array
    {
        $out = [];
        foreach (self::map($data, $key) as $k => $item) {
            $out[$k] = $factory(self::map([$k => $item], $k));
        }

        return $out;
    }

    /**
     * Hydrates a top-level JSON array of objects (list endpoints).
     *
     * @template T
     *
     * @param list<mixed>                          $items
     * @param callable(array<string, mixed>): T    $factory
     *
     * @return list<T>
     */
    public static function listOf(array $items, callable $factory): array
    {
        return self::objectList(['items' => $items], 'items', $factory);
    }
}
