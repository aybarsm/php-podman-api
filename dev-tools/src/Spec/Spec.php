<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * One parsed Podman swagger document (resources/podman/swagger-v{version}.yaml).
 */
final class Spec
{
    private const array HTTP_METHODS = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];

    /** @var array<string, SpecOperation>|null */
    private ?array $operations = null;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $version,
        private readonly array $raw,
    ) {}

    public static function fromFile(string $version, string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Spec file not found: {$path}");
        }

        return new self($version, Node::map(Yaml::parseFile($path)));
    }

    /**
     * @return array<string, SpecOperation> keyed by operationId, sorted
     */
    public function operations(): array
    {
        if ($this->operations !== null) {
            return $this->operations;
        }

        $ops = [];
        foreach (Node::map($this->raw['paths'] ?? null) as $path => $methods) {
            foreach (Node::map($methods) as $method => $node) {
                if (! in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }
                $op = SpecOperation::fromNode($method, $path, Node::map($node), $this);
                $ops[$op->id] = $op;
            }
        }
        ksort($ops);

        return $this->operations = $ops;
    }

    /**
     * @return array<string, SpecOperation>
     */
    public function libpodOperations(): array
    {
        return array_filter($this->operations(), static fn (SpecOperation $op): bool => $op->isLibpod());
    }

    public function operation(string $id): ?SpecOperation
    {
        return $this->operations()[$id] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        $defs = array_map(Node::map(...), Node::map($this->raw['definitions'] ?? null));
        ksort($defs);

        return $defs;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $name): ?array
    {
        return $this->definitions()[$name] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sharedResponse(string $name): ?array
    {
        $responses = Node::map($this->raw['responses'] ?? null);

        return isset($responses[$name]) ? Node::map($responses[$name]) : null;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function describeResponse(array $response): string
    {
        if (isset($response['$ref'])) {
            $name = SchemaRef::name(Node::string($response['$ref']));
            $shared = $this->sharedResponse($name);

            return $shared === null ? $name : $name.': '.($this->describeResponseSchema($shared) ?? 'no body');
        }

        return $this->describeResponseSchema($response) ?? 'no body';
    }

    /**
     * Names of all definitions transitively reachable from the given operations.
     *
     * @param iterable<SpecOperation> $operations
     *
     * @return list<string>
     */
    public function reachableDefinitions(iterable $operations): array
    {
        $queue = [];
        foreach ($operations as $op) {
            array_push($queue, ...SchemaRef::refsIn($op->raw));
        }

        $seen = [];
        while ($queue !== []) {
            $ref = array_shift($queue);
            if (isset($seen[$ref])) {
                continue;
            }
            $seen[$ref] = true;

            $name = SchemaRef::name($ref);
            $node = str_starts_with($ref, '#/responses/') ? $this->sharedResponse($name) : $this->definition($name);
            array_push($queue, ...SchemaRef::refsIn($node));
        }

        $defs = [];
        foreach (array_keys($seen) as $ref) {
            if (str_starts_with($ref, '#/definitions/')) {
                $defs[] = SchemaRef::name($ref);
            }
        }
        sort($defs);

        return $defs;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function describeResponseSchema(array $response): ?string
    {
        return SchemaRef::describe(Node::map($response['schema'] ?? null));
    }
}
