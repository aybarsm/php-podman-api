<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Internal\Support;

use Aybarsm\Podman\Api\Dto\Shared\Filters;
use JsonException;

/**
 * Encodes query parameters the way Podman's gorilla/schema decoder expects them.
 *
 * - null values are omitted
 * - booleans become "true" / "false"
 * - lists repeat the key (?names=a&names=b)
 * - Filters become a JSON object; empty Filters are omitted
 *
 * @internal
 */
final class Query
{
    /**
     * @param array<string, scalar|list<scalar>|Filters|null> $params
     *
     * @throws JsonException
     */
    public static function build(array $params): string
    {
        $pairs = [];
        foreach ($params as $name => $value) {
            foreach (self::values($value) as $encoded) {
                $pairs[] = rawurlencode($name).'='.rawurlencode($encoded);
            }
        }

        return implode('&', $pairs);
    }

    /**
     * For parameters the spec documents as a JSON-encoded value inside the query string
     * (e.g. `rename`, `labels`, `driveropts`).
     *
     * @param array<array-key, mixed>|null $value
     *
     * @throws JsonException
     */
    public static function json(?array $value): ?string
    {
        return $value === null ? null : json_encode($value === [] ? (object) [] : $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param scalar|list<scalar>|Filters|null $value
     *
     * @return list<string>
     *
     * @throws JsonException
     */
    private static function values(mixed $value): array
    {
        return match (true) {
            $value === null => [],
            $value instanceof Filters => $value->isEmpty() ? [] : [json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)],
            is_array($value) => array_map(self::scalar(...), $value),
            default => [self::scalar($value)],
        };
    }

    private static function scalar(bool|int|float|string $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
