<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Generator;

use Aybarsm\Podman\Api\Dev\Spec\DefinitionResolver;
use Aybarsm\Podman\Api\Dev\Spec\Node;
use Aybarsm\Podman\Api\Dev\Spec\SchemaRef;
use Aybarsm\Podman\Api\Dev\Spec\Spec;
use Aybarsm\Podman\Api\Dev\Spec\SpecOperation;
use RuntimeException;

/**
 * Prints starting-point PHP for a DTO or a resource. Output is meant to be hand-curated, never committed blindly.
 */
final readonly class Scaffolder
{
    private const array IDENTITY_KEYS = ['Id', 'ID', 'Name', 'name', 'id'];

    public function __construct(private Spec $spec, private DefinitionResolver $resolver) {}

    public function dto(string $definition, string $namespace, ?string $className = null): string
    {
        $resolved = $this->resolver->resolve($this->spec, $definition)
            ?? throw new RuntimeException("Unknown or shapeless definition: {$definition}");
        $def = $resolved['definition'];
        foreach (Node::list($def['allOf'] ?? null) as $part) {
            $def['properties'] = [...Node::map($def['properties'] ?? null), ...Node::map(Node::map($part)['properties'] ?? null)];
        }
        $class = $className ?? self::className($definition);
        $see = "resources/podman/swagger-v{$resolved['version']}.yaml#/definitions/{$resolved['name']}"
            .($resolved['version'] !== $this->spec->version ? " (degraded in v{$this->spec->version})" : '');

        $required = [];
        $optional = [];
        $docParams = [];
        $args = [];
        $usesDateTime = false;
        foreach (Node::map($def['properties'] ?? null) as $key => $schema) {
            $schema = Node::map($schema);
            [$type, $doc, $expr] = $this->mapType($schema, $key);
            $prop = self::propertyName($key);
            $description = self::firstLine(Node::string($schema['description'] ?? null));
            $usesDateTime = $usesDateTime || str_contains($type, 'DateTimeImmutable');

            // Identity fields are non-null (Data::string throws when missing); Go omits `false`, so bools default false.
            if (in_array($key, self::IDENTITY_KEYS, true) && $type === '?string') {
                [$type, $expr] = ['string', "Data::string(\$data, '{$key}')"];
            } elseif ($type === '?bool') {
                [$type, $expr] = ['bool', "Data::bool(\$data, '{$key}')"];
            }

            if ($doc !== null) {
                $docParams[] = [$doc, '$'.$prop, $description];
            }
            $comment = $doc === null && $description !== '' ? "        /** {$description} */\n" : '';
            $default = match (true) {
                str_starts_with($type, '?') => ' = null',
                $type === 'array' => ' = []',
                $type === 'bool' => ' = false',
                default => '',
            };
            $line = $comment."        public {$type} \${$prop}{$default},";
            $default === '' ? $required[] = $line : $optional[] = $line;
            $args[] = "            {$prop}: {$expr},";
        }

        $summary = self::firstLine(Node::string($def['description'] ?? $def['title'] ?? null));
        $paramsCode = implode("\n", [...$required, ...$optional]);
        $argsCode = implode("\n", $args);
        $docBlock = self::paramDocBlock($docParams);
        $dateTimeUse = $usesDateTime ? "use DateTimeImmutable;\n" : '';

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Aybarsm\\Podman\\Api\\Dto\\{$namespace};

            use Aybarsm\\Podman\\Api\\Contracts\\Hydratable;
            use Aybarsm\\Podman\\Api\\Internal\\Support\\Data;
            {$dateTimeUse}use Override;

            /**
             * {$summary}
             *
             * @see {$see}
             */
            final readonly class {$class} implements Hydratable
            {
            {$docBlock}    public function __construct(
            {$paramsCode}
                ) {}

                #[Override]
                public static function fromArray(array \$data): static
                {
                    return new self(
            {$argsCode}
                    );
                }
            }

            PHP;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $params [type, $name, description]
     */
    private static function paramDocBlock(array $params): string
    {
        if ($params === []) {
            return '';
        }
        $typeWidth = max(array_map(static fn (array $p): int => strlen($p[0]), $params));
        $nameWidth = max(array_map(static fn (array $p): int => strlen($p[1]), $params));
        $lines = array_map(
            static fn (array $p): string => rtrim(sprintf("     * @param %-{$typeWidth}s %-{$nameWidth}s %s", ...$p)),
            $params,
        );

        return "    /**\n".implode("\n", $lines)."\n     */\n";
    }

    public function resource(string $tag, string $className): string
    {
        $ops = array_filter(
            $this->spec->libpodOperations(),
            static fn (SpecOperation $op): bool => in_array($tag, $op->tags, true),
        );
        if ($ops === []) {
            throw new RuntimeException("No Libpod operations tagged '{$tag}'");
        }

        $methods = [];
        foreach ($ops as $op) {
            $methods[] = $this->resourceMethod($op);
        }
        $methodsCode = implode("\n\n", $methods);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Aybarsm\\Podman\\Api\\Resources;

            use Aybarsm\\Podman\\Api\\Internal\\Operation;
            use LogicException;

            final readonly class {$className} extends AbstractResource
            {
            {$methodsCode}
            }

            PHP;
    }

    private function resourceMethod(SpecOperation $op): string
    {
        $doc = ["    /**", '     * '.($op->summary !== '' ? $op->summary : $op->id), '     *', "     * {$op->method} {$op->path}"];
        $args = [];
        foreach ($op->parameters as $p) {
            $doc[] = sprintf('     * @todo %s %s: %s%s', $p->in, $p->name, $p->signature(), $p->description !== '' ? ' — '.self::firstLine($p->description) : '');
            if ($p->in === 'path') {
                $args[] = 'string $'.self::propertyName($p->name);
            }
        }
        foreach ($op->responses as $code => $desc) {
            $doc[] = "     * @todo response {$code}: {$desc}";
        }
        $doc[] = '     */';

        $name = self::methodName($op->caseName());
        $argList = implode(', ', $args);

        return implode("\n", $doc)."\n".<<<PHP
                public function {$name}({$argList}): never
                {
                    // \$this->transport->send(Operation::{$op->caseName()}, …)
                    throw new LogicException('Not implemented: {$op->id}');
                }
            PHP;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array{0: string, 1: string|null, 2: string} [php type, phpdoc type, hydration expression]
     */
    private function mapType(array $schema, string $key, int $depth = 0): array
    {
        $k = "'".addcslashes($key, "'\\")."'";

        if (isset($schema['$ref'])) {
            $name = SchemaRef::name(Node::string($schema['$ref']));
            $target = $this->spec->definition($name) ?? [];
            $targetType = Node::string($target['type'] ?? null, 'object');
            if ($depth < 3 && ($targetType !== 'object' || isset($target['additionalProperties'])) && ! isset($target['properties'])) {
                return $this->mapType($target, $key, $depth + 1);
            }
            $class = self::className($name);

            return ["?{$class}", null, "Data::objectOrNull(\$data, {$k}, {$class}::fromArray(...))"];
        }

        $type = Node::string($schema['type'] ?? null, 'object');
        $format = Node::string($schema['format'] ?? null);

        return match (true) {
            $type === 'string' && $format === 'date-time' => ['?DateTimeImmutable', null, "Data::dateTimeOrNull(\$data, {$k})"],
            $type === 'string' => ['?string', null, "Data::stringOrNull(\$data, {$k})"],
            $type === 'integer' => ['?int', null, "Data::intOrNull(\$data, {$k})"],
            $type === 'number' => ['?float', null, "Data::floatOrNull(\$data, {$k})"],
            $type === 'boolean' => ['?bool', null, "Data::boolOrNull(\$data, {$k})"],
            $type === 'array' => $this->mapList(Node::map($schema['items'] ?? null), $k),
            isset($schema['additionalProperties']) => $this->mapMap(Node::map($schema['additionalProperties']), $k),
            default => ['array', 'array<string, mixed>', "Data::map(\$data, {$k})"],
        };
    }

    /**
     * @param array<string, mixed> $items
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function mapList(array $items, string $k): array
    {
        if (isset($items['$ref'])) {
            $class = self::className(SchemaRef::name(Node::string($items['$ref'])));

            return ['array', "list<{$class}>", "Data::objectList(\$data, {$k}, {$class}::fromArray(...))"];
        }

        return match (Node::string($items['type'] ?? null)) {
            'string' => ['array', 'list<string>', "Data::stringList(\$data, {$k})"],
            'integer' => ['array', 'list<int>', "Data::intList(\$data, {$k})"],
            default => ['array', 'list<mixed>', "Data::list(\$data, {$k})"],
        };
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function mapMap(array $values, string $k): array
    {
        if (isset($values['$ref'])) {
            $class = self::className(SchemaRef::name(Node::string($values['$ref'])));

            return ['array', "array<string, {$class}>", "Data::objectMap(\$data, {$k}, {$class}::fromArray(...))"];
        }

        return Node::string($values['type'] ?? null) === 'string'
            ? ['array', 'array<string, string>', "Data::stringMap(\$data, {$k})"]
            : ['array', 'array<string, mixed>', "Data::map(\$data, {$k})"];
    }

    /**
     * 'ContainerList' → 'list', 'ContainerDelete' → 'remove', 'ImageDeleteAll' → 'removeMany' (see conventions.md).
     */
    public static function methodName(string $caseName): string
    {
        $verb = preg_replace('/^(Container|Image|Pod|Network|Volume|Secret|Manifest|Exec|System|Artifact|Quadlet)s?/', '', $caseName);
        $verb = $verb === null || $verb === '' ? $caseName : $verb;

        return match ($verb) {
            'Delete' => 'remove',
            'DeleteAll' => 'removeMany',
            default => lcfirst($verb),
        };
    }

    public static function className(string $definition): string
    {
        return preg_replace('/Libpod$/', '', ucfirst($definition)) ?? $definition;
    }

    /**
     * JSON key → camelCase property ('ImageID' → 'imageId', 'IPAddress' → 'ipAddress', 'cpu_percent' → 'cpuPercent').
     */
    public static function propertyName(string $key): string
    {
        $s = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $key) ?? $key;
        $s = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $s) ?? $s;
        $parts = preg_split('/[_\-\s.]+/', strtolower($s), -1, PREG_SPLIT_NO_EMPTY) ?: [$key];

        return lcfirst(implode('', array_map(ucfirst(...), $parts)));
    }

    private static function firstLine(string $text): string
    {
        $line = trim(strtok($text, "\n") ?: '');

        return str_replace('*/', '* /', $line);
    }
}
