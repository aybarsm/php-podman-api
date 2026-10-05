<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

final readonly class SpecOperation
{
    private const string LIBPOD_PREFIX = '/libpod/';

    /**
     * @param list<string>                 $tags
     * @param list<SpecParameter>          $parameters
     * @param array<string, string>        $responses status code => response type description
     * @param list<string>                 $produces
     * @param list<string>                 $consumes
     * @param array<string, mixed>         $raw
     */
    public function __construct(
        public string $id,
        public string $method,
        public string $path,
        public array $tags,
        public string $summary,
        public string $description,
        public array $parameters,
        public array $responses,
        public array $produces,
        public array $consumes,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $node
     */
    public static function fromNode(string $method, string $path, array $node, Spec $spec): self
    {
        $responses = [];
        foreach (Node::map($node['responses'] ?? null) as $code => $response) {
            $responses[$code] = $spec->describeResponse(Node::map($response));
        }

        return new self(
            id: Node::string($node['operationId'] ?? null),
            method: strtoupper($method),
            path: $path,
            tags: Node::strings($node['tags'] ?? null),
            summary: trim(Node::string($node['summary'] ?? null)),
            description: trim(Node::string($node['description'] ?? null)),
            parameters: array_map(SpecParameter::fromNode(...), Node::list($node['parameters'] ?? null)),
            responses: $responses,
            produces: Node::strings($node['produces'] ?? null),
            consumes: Node::strings($node['consumes'] ?? null),
            raw: $node,
        );
    }

    public function isLibpod(): bool
    {
        return str_starts_with($this->path, self::LIBPOD_PREFIX);
    }

    /**
     * Enum case name used in src/Internal/Operation.php ('ContainerListLibpod' → 'ContainerList').
     */
    public function caseName(): string
    {
        return str_ends_with($this->id, 'Libpod') ? substr($this->id, 0, -6) : $this->id;
    }

    public function primaryTag(): string
    {
        foreach ($this->tags as $tag) {
            if (! str_ends_with($tag, '(compat)')) {
                return $tag;
            }
        }

        return isset($this->tags[0]) ? str_replace(' (compat)', '', $this->tags[0]) : 'untagged';
    }

    /**
     * @return array<string, SpecParameter> keyed by "in:name"
     */
    public function parametersByKey(): array
    {
        $out = [];
        foreach ($this->parameters as $p) {
            $out[$p->in.':'.$p->name] = $p;
        }
        ksort($out);

        return $out;
    }
}
