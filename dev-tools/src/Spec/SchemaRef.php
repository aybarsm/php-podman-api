<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

/**
 * Renders a compact, human-readable type for a Swagger schema node.
 */
final class SchemaRef
{
    /**
     * @param array<string, mixed> $schema
     */
    public static function describe(array $schema): ?string
    {
        if ($schema === []) {
            return null;
        }

        if (isset($schema['$ref'])) {
            return self::name(Node::string($schema['$ref']));
        }

        if (isset($schema['allOf'])) {
            $parts = array_map(
                static fn (mixed $s): string => self::describe(Node::map($s)) ?? 'object',
                Node::list($schema['allOf']),
            );

            return implode('&', $parts);
        }

        $type = Node::string($schema['type'] ?? null, 'object');

        if ($type === 'array') {
            return 'list<'.(self::describe(Node::map($schema['items'] ?? null)) ?? 'mixed').'>';
        }

        if ($type === 'object' && isset($schema['additionalProperties'])) {
            return 'map<'.(self::describe(Node::map($schema['additionalProperties'])) ?? 'mixed').'>';
        }

        $format = Node::nullableString($schema['format'] ?? null);

        return $format !== null ? $type.'('.$format.')' : $type;
    }

    /**
     * '#/definitions/ListContainer' → 'ListContainer'.
     */
    public static function name(string $ref): string
    {
        $pos = strrpos($ref, '/');

        return $pos === false ? $ref : substr($ref, $pos + 1);
    }

    /**
     * Every '#/definitions/*' and '#/responses/*' reference reachable inside a node.
     *
     * @return list<string>
     */
    public static function refsIn(mixed $node): array
    {
        $refs = [];
        $walk = static function (mixed $n) use (&$walk, &$refs): void {
            if (! is_array($n)) {
                return;
            }
            foreach ($n as $k => $v) {
                if ($k === '$ref' && is_string($v)) {
                    $refs[] = $v;
                } else {
                    $walk($v);
                }
            }
        };
        $walk($node);

        return array_values(array_unique($refs));
    }
}
