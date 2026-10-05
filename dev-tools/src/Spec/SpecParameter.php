<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

final readonly class SpecParameter
{
    /**
     * @param list<string>|null $enum
     */
    public function __construct(
        public string $name,
        public string $in,
        public string $type,
        public bool $required,
        public string $description,
        public mixed $default,
        public ?array $enum,
        public ?string $itemsType,
        public ?string $schemaRef,
    ) {}

    public static function fromNode(mixed $node): self
    {
        $n = Node::map($node);
        $schema = Node::map($n['schema'] ?? null);
        $items = Node::map($n['items'] ?? null);

        return new self(
            name: Node::string($n['name'] ?? null),
            in: Node::string($n['in'] ?? null),
            type: Node::string($n['type'] ?? null, isset($n['schema']) ? 'schema' : 'unknown'),
            required: Node::bool($n['required'] ?? null),
            description: trim(Node::string($n['description'] ?? null)),
            default: $n['default'] ?? null,
            enum: isset($n['enum']) ? Node::strings($n['enum']) : null,
            itemsType: Node::nullableString($items['type'] ?? null),
            schemaRef: SchemaRef::describe($schema),
        );
    }

    /**
     * Stable signature used when diffing parameters across spec versions.
     */
    public function signature(): string
    {
        return sprintf(
            '%s:%s%s%s%s',
            $this->in,
            $this->type,
            $this->itemsType !== null ? '<'.$this->itemsType.'>' : '',
            $this->schemaRef !== null ? '('.$this->schemaRef.')' : '',
            $this->required ? ' required' : '',
        );
    }
}
