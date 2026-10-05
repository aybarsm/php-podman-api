<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

/**
 * Some specs (notably v5.8) contain definitions emitted as bare `type: object` with no properties. This resolver
 * finds the newest older spec that still describes the shape, either under the same name or, for renames, under the
 * name that occupies the same position in the same operation's response.
 */
final readonly class DefinitionResolver
{
    /**
     * @param array<string, Spec> $specs version => spec, ascending
     */
    public function __construct(private array $specs) {}

    /**
     * True for a bare `type: object` with no shape, or an allOf composed from such a definition.
     *
     * @param array<string, mixed> $definition
     */
    public static function isDegraded(array $definition, Spec $spec, int $depth = 0): bool
    {
        $alias = Node::nullableString($definition['$ref'] ?? null);
        if ($alias !== null) {
            $target = $spec->definition(SchemaRef::name($alias));

            return $target === null || ($depth < 5 && self::isDegraded($target, $spec, $depth + 1));
        }

        if (isset($definition['allOf'])) {
            foreach (Node::list($definition['allOf']) as $part) {
                $ref = Node::nullableString(Node::map($part)['$ref'] ?? null);
                $target = $ref !== null ? $spec->definition(SchemaRef::name($ref)) : null;
                if ($target !== null && $depth < 5 && self::isDegraded($target, $spec, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        return Node::string($definition['type'] ?? null, 'object') === 'object'
            && ! isset($definition['properties'])
            && ! isset($definition['additionalProperties']);
    }

    /**
     * @return list<string> degraded definitions reachable from Libpod operations of the given spec
     */
    public function degraded(Spec $spec): array
    {
        return array_values(array_filter(
            $spec->reachableDefinitions($spec->libpodOperations()),
            fn (string $name): bool => self::isDegraded($spec->definition($name) ?? [], $spec),
        ));
    }

    /**
     * @return array{version: string, name: string, definition: array<string, mixed>}|null
     */
    public function resolve(Spec $spec, string $name, int $depth = 0): ?array
    {
        $definition = $spec->definition($name);

        // Pure alias (`name: {$ref: '#/definitions/Other'}`): describe the target instead.
        $alias = Node::nullableString($definition['$ref'] ?? null);
        if ($alias !== null && $depth < 5) {
            return $this->resolve($spec, SchemaRef::name($alias), $depth + 1);
        }

        if ($definition === null) {
            // Referenced only from a shape that itself came from an older spec.
            foreach ($this->olderThan($spec->version) as $older) {
                $candidate = $older->definition($name);
                if ($candidate !== null && ! self::isDegraded($candidate, $older)) {
                    return ['version' => $older->version, 'name' => $name, 'definition' => $candidate];
                }
            }

            return null;
        }
        if (! self::isDegraded($definition, $spec)) {
            return ['version' => $spec->version, 'name' => $name, 'definition' => $definition];
        }

        foreach ($this->olderThan($spec->version) as $older) {
            $candidate = $older->definition($name);
            if ($candidate !== null && ! self::isDegraded($candidate, $older)) {
                return ['version' => $older->version, 'name' => $name, 'definition' => $candidate];
            }

            $renamed = $this->renamedIn($spec, $older, $name);
            $candidate = $renamed !== null ? $older->definition($renamed) : null;
            if ($renamed !== null && $candidate !== null && ! self::isDegraded($candidate, $older)) {
                return ['version' => $older->version, 'name' => $renamed, 'definition' => $candidate];
            }
        }

        return null;
    }

    /**
     * Finds the definition name used by $older at the position where $spec references $name in an operation response.
     */
    private function renamedIn(Spec $spec, Spec $older, string $name): ?string
    {
        foreach ($spec->libpodOperations() as $id => $op) {
            $previous = $older->operation($id);
            if ($previous === null) {
                continue;
            }
            foreach ($op->responses as $code => $desc) {
                $before = $previous->responses[$code] ?? null;
                if ($before === null || $before === $desc) {
                    continue;
                }
                $now = self::typeNames($desc);
                $then = self::typeNames($before);
                $pos = array_search($name, $now, true);
                if ($pos !== false && count($now) === count($then) && isset($then[$pos])) {
                    return $then[$pos];
                }
            }
        }

        return null;
    }

    /**
     * 'imageListLibpod: list<ImageSummary>' → ['ImageSummary'].
     *
     * @return list<string>
     */
    private static function typeNames(string $description): array
    {
        $schema = substr($description, (strpos($description, ': ') ?: -2) + 2);
        preg_match_all('/\b([A-Z]\w*)\b/', $schema, $m);

        return $m[1];
    }

    /**
     * @return list<Spec> newest first
     */
    private function olderThan(string $version): array
    {
        $out = [];
        foreach (array_reverse($this->specs, true) as $v => $spec) {
            if (version_compare((string) $v, $version, '<')) {
                $out[] = $spec;
            }
        }

        return $out;
    }
}
