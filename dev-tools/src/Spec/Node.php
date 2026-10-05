<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

/**
 * Typed narrowing helpers for the untyped arrays produced by the YAML parser.
 */
final class Node
{
    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $v): string => self::scalarString($v),
            array_filter(self::list($value), static fn (mixed $v): bool => is_scalar($v)),
        ));
    }

    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? self::scalarString($value) : $default;
    }

    public static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? self::scalarString($value) : null;
    }

    public static function bool(mixed $value): bool
    {
        return $value === true;
    }

    private static function scalarString(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
