<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Console;

use Aybarsm\Podman\Api\Dev\Coverage;
use Aybarsm\Podman\Api\Dev\Generator\OperationEnumGenerator;
use Aybarsm\Podman\Api\Dev\Generator\Scaffolder;
use Aybarsm\Podman\Api\Dev\Spec\DefinitionResolver;
use Aybarsm\Podman\Api\Dev\Spec\Node;
use Aybarsm\Podman\Api\Dev\Spec\SchemaRef;
use Aybarsm\Podman\Api\Dev\Spec\SinceCalculator;
use Aybarsm\Podman\Api\Dev\Spec\Spec;
use Aybarsm\Podman\Api\Dev\Spec\SpecDiff;
use Aybarsm\Podman\Api\Dev\Spec\SpecOperation;
use Aybarsm\Podman\Api\Dev\Spec\SpecRepository;
use RuntimeException;
use Throwable;

/**
 * `bin/spec <command>`: spec inspection, diffing, scaffolding and coverage for the Podman swagger files.
 */
final class Application
{
    private const string USAGE = <<<'TXT'
        Usage: bin/spec <command> [args] [--spec=X.Y]

        Commands:
          versions                         List available spec versions
          ops [--tag=T] [--all]            List Libpod operations (--all includes compat) with their minimum version
          show <OperationId|Definition>    Show an operation (params, responses) or a definition (properties)
          diff <from> <to> [--all]         Structural diff between two spec versions (Libpod-reachable by default)
          degraded [--spec=X.Y]            Libpod definitions emitted without a shape, and the older spec that describes them
          since                            First spec version for every Libpod operation in the newest spec
          operation-enum [--check]         (Re)generate src/Internal/Operation.php; --check fails if it is stale
          scaffold dto <Definition> <Namespace> [ClassName]
                                           Print a starting-point readonly DTO for a definition
          scaffold resource <tag> <ClassName>
                                           Print a starting-point resource with one stub per Libpod operation
          coverage [-v]                    Implemented / deferred / missing Libpod operations per tag

        --spec=X.Y selects the spec for ops/show/scaffold/coverage (default: newest).
        show/scaffold transparently fall back to an older spec when a definition is degraded (see `degraded`).
        TXT;

    private readonly SpecRepository $specs;

    public function __construct(private readonly string $root)
    {
        $this->specs = new SpecRepository($root.'/resources/podman');
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        [$args, $opts] = self::parse(array_slice($argv, 1));
        $command = array_shift($args) ?? 'help';

        try {
            return match ($command) {
                'versions' => $this->versions(),
                'ops' => $this->ops($opts),
                'show' => $this->show($args, $opts),
                'diff' => $this->diff($args, $opts),
                'since' => $this->since(),
                'degraded' => $this->degraded($opts),
                'operation-enum' => $this->operationEnum($opts),
                'scaffold' => $this->scaffold($args, $opts),
                'coverage' => $this->coverage($opts),
                default => $this->help(),
            };
        } catch (Throwable $e) {
            fwrite(STDERR, 'error: '.$e->getMessage().PHP_EOL);

            return 1;
        }
    }

    private function help(): int
    {
        echo self::USAGE.PHP_EOL;

        return 0;
    }

    private function versions(): int
    {
        foreach ($this->specs->versions() as $v) {
            echo $v.PHP_EOL;
        }

        return 0;
    }

    /**
     * @param array<string, string|true> $opts
     */
    private function ops(array $opts): int
    {
        $spec = $this->spec($opts);
        $since = SinceCalculator::compute($this->specs->all());
        $tag = is_string($opts['tag'] ?? null) ? $opts['tag'] : null;
        $ops = isset($opts['all']) ? $spec->operations() : $spec->libpodOperations();

        foreach ($ops as $op) {
            if ($tag !== null && ! in_array($tag, $op->tags, true)) {
                continue;
            }
            printf("%-6s %-50s %-32s %s\n", $op->method, $op->path, $op->id, isset($since[$op->id]) ? 'since '.$since[$op->id] : '');
        }

        return 0;
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|true> $opts
     */
    private function show(array $args, array $opts): int
    {
        $name = $args[0] ?? throw new RuntimeException('show requires an operationId or definition name');
        $spec = $this->spec($opts);

        $op = $spec->operation($name.'Libpod') ?? $spec->operation($name);
        if ($op !== null) {
            $this->printOperation($op, $spec);

            return 0;
        }

        $resolved = $this->resolver()->resolve($spec, $name)
            ?? throw new RuntimeException("No operation or definition named '{$name}' in v{$spec->version}, and no older spec describes it");
        $def = $resolved['definition'];
        echo "definition {$name} (v{$spec->version})".PHP_EOL;
        if ($resolved['version'] !== $spec->version) {
            echo "  ⚠ degraded in v{$spec->version}; shape taken from v{$resolved['version']} {$resolved['name']}".PHP_EOL;
        } elseif ($resolved['name'] !== $name) {
            echo "  alias of {$resolved['name']}".PHP_EOL;
        }
        foreach (Node::list($def['allOf'] ?? null) as $part) {
            echo '  allOf: '.(SchemaRef::describe(Node::map($part)) ?? 'object').PHP_EOL;
            $def['properties'] = [...Node::map($def['properties'] ?? null), ...Node::map(Node::map($part)['properties'] ?? null)];
        }
        echo '  type: '.(SchemaRef::describe(array_diff_key($def, ['properties' => 1])) ?? 'object').PHP_EOL;
        $desc = trim(Node::string($def['description'] ?? $def['title'] ?? null));
        if ($desc !== '') {
            echo '  '.str_replace("\n", "\n  ", $desc).PHP_EOL;
        }
        $required = Node::strings($def['required'] ?? null);
        foreach (Node::map($def['properties'] ?? null) as $prop => $schema) {
            $schema = Node::map($schema);
            printf(
                "  %-32s %-40s %s%s\n",
                $prop,
                SchemaRef::describe($schema) ?? 'mixed',
                in_array($prop, $required, true) ? '[required] ' : '',
                strtok(Node::string($schema['description'] ?? null), "\n") ?: '',
            );
        }

        return 0;
    }

    private function printOperation(SpecOperation $op, Spec $spec): void
    {
        $paramSince = $this->parameterSince($op);
        echo "{$op->id}  ({$op->method} {$op->path})".PHP_EOL;
        echo '  tags: '.implode(', ', $op->tags).PHP_EOL;
        if ($op->summary !== '') {
            echo '  summary: '.$op->summary.PHP_EOL;
        }
        if ($op->description !== '') {
            echo '  '.str_replace("\n", "\n  ", $op->description).PHP_EOL;
        }
        echo '  produces: '.implode(', ', $op->produces).PHP_EOL;
        echo '  parameters:'.PHP_EOL;
        foreach ($op->parameters as $p) {
            $default = $p->default !== null ? ' default='.json_encode($p->default) : '';
            $enum = $p->enum !== null ? ' enum=['.implode('|', $p->enum).']' : '';
            $since = isset($paramSince[$p->in.':'.$p->name]) ? ' [since '.$paramSince[$p->in.':'.$p->name].']' : '';
            printf("    %-7s %-24s %-28s%s%s%s\n", $p->in, $p->name, $p->signature(), $default, $enum, $since);
            if ($p->description !== '') {
                echo '            '.str_replace("\n", "\n            ", $p->description).PHP_EOL;
            }
        }
        foreach (Node::list($op->raw['parameters'] ?? null) as $raw) {
            $raw = Node::map($raw);
            if (($raw['in'] ?? null) === 'body') {
                $this->printInlineProperties('body '.Node::string($raw['name'] ?? null), Node::map($raw['schema'] ?? null));
            }
        }
        echo '  responses:'.PHP_EOL;
        foreach ($op->responses as $code => $desc) {
            echo "    {$code}: {$desc}".PHP_EOL;
        }
        foreach (Node::map($op->raw['responses'] ?? null) as $code => $raw) {
            $this->printInlineProperties("response {$code}", Node::map(Node::map($raw)['schema'] ?? null));
        }
    }

    /**
     * Inline (non-$ref) object schemas are invisible in the one-line summaries; print their properties.
     *
     * @param array<string, mixed> $schema
     */
    private function printInlineProperties(string $label, array $schema): void
    {
        $properties = Node::map($schema['properties'] ?? null);
        if ($properties === []) {
            return;
        }
        echo "  inline schema ({$label}):".PHP_EOL;
        foreach ($properties as $prop => $propSchema) {
            $propSchema = Node::map($propSchema);
            printf(
                "    %-30s %-36s %s\n",
                $prop,
                SchemaRef::describe($propSchema) ?? 'mixed',
                strtok(Node::string($propSchema['description'] ?? null), "\n") ?: '',
            );
        }
    }

    /**
     * First spec version of each parameter, for parameters newer than the oldest spec that has the operation.
     *
     * @return array<string, string> "in:name" => version
     */
    private function parameterSince(SpecOperation $op): array
    {
        $versions = array_reverse($this->specs->all(), true);
        $out = [];
        foreach (array_keys($op->parametersByKey()) as $key) {
            $first = null;
            foreach ($versions as $version => $spec) {
                $older = $spec->operation($op->id);
                if ($older === null || ! isset($older->parametersByKey()[$key])) {
                    break;
                }
                $first = (string) $version;
            }
            $oldestWithOp = null;
            foreach ($versions as $version => $spec) {
                if ($spec->operation($op->id) === null) {
                    break;
                }
                $oldestWithOp = (string) $version;
            }
            if ($first !== null && $first !== $oldestWithOp) {
                $out[$key] = $first;
            }
        }

        return $out;
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|true> $opts
     */
    private function diff(array $args, array $opts): int
    {
        if (count($args) < 2) {
            throw new RuntimeException('diff requires <from> <to>, e.g. bin/spec diff 5.7 5.8');
        }
        echo SpecDiff::compare($this->specs->get($args[0]), $this->specs->get($args[1]), ! isset($opts['all']))->render();

        return 0;
    }

    private function since(): int
    {
        $floor = $this->specs->versions()[0] ?? '';
        foreach (SinceCalculator::compute($this->specs->all()) as $id => $version) {
            if ($version !== $floor) {
                printf("%-32s %s\n", $id, $version);
            }
        }
        echo "(all other Libpod operations: since {$floor})".PHP_EOL;

        return 0;
    }

    /**
     * @param array<string, string|true> $opts
     */
    private function degraded(array $opts): int
    {
        $spec = $this->spec($opts);
        $resolver = $this->resolver();
        foreach ($resolver->degraded($spec) as $name) {
            $resolved = $resolver->resolve($spec, $name);
            printf(
                "%-32s %s\n",
                $name,
                $resolved === null ? 'no shape in any spec' : "→ v{$resolved['version']} {$resolved['name']}",
            );
        }

        return 0;
    }

    private function resolver(): DefinitionResolver
    {
        return new DefinitionResolver($this->specs->all());
    }

    /**
     * @param array<string, string|true> $opts
     */
    private function operationEnum(array $opts): int
    {
        $versions = $this->specs->versions();
        $code = OperationEnumGenerator::generate(
            $this->specs->latest(),
            SinceCalculator::compute($this->specs->all()),
            $versions[0] ?? throw new RuntimeException('No specs found'),
            SinceCalculator::queryParameters($this->specs->all()),
        );

        $target = $this->root.'/'.OperationEnumGenerator::TARGET;
        if (isset($opts['check'])) {
            if (! is_file($target) || file_get_contents($target) !== $code) {
                fwrite(STDERR, OperationEnumGenerator::TARGET.' is stale. Run: bin/spec operation-enum'.PHP_EOL);

                return 1;
            }
            echo OperationEnumGenerator::TARGET.' is up to date.'.PHP_EOL;

            return 0;
        }

        file_put_contents($target, $code);
        printf("Wrote %s (%d operations).\n", OperationEnumGenerator::TARGET, count($this->specs->latest()->libpodOperations()));

        return 0;
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|true> $opts
     */
    private function scaffold(array $args, array $opts): int
    {
        $scaffolder = new Scaffolder($this->spec($opts), $this->resolver());

        echo match ($args[0] ?? '') {
            'dto' => $scaffolder->dto(
                $args[1] ?? throw new RuntimeException('scaffold dto <Definition> <Namespace> [ClassName]'),
                $args[2] ?? throw new RuntimeException('scaffold dto <Definition> <Namespace> [ClassName]'),
                $args[3] ?? null,
            ),
            'resource' => $scaffolder->resource(
                $args[1] ?? throw new RuntimeException('scaffold resource <tag> <ClassName>'),
                $args[2] ?? throw new RuntimeException('scaffold resource <tag> <ClassName>'),
            ),
            default => throw new RuntimeException('scaffold dto|resource …'),
        };

        return 0;
    }

    /**
     * @param array<string, string|true> $opts
     */
    private function coverage(array $opts): int
    {
        /** @var array<string, string> $deferred */
        $deferred = require $this->root.'/dev-tools/deferred-operations.php';
        echo Coverage::compute($this->spec($opts), $this->root.'/src/Resources', $deferred)
            ->render(isset($opts['v']) || isset($opts['verbose']));

        return 0;
    }

    /**
     * @param array<string, string|true> $opts
     */
    private function spec(array $opts): Spec
    {
        return is_string($opts['spec'] ?? null) ? $this->specs->get($opts['spec']) : $this->specs->latest();
    }

    /**
     * @param list<string> $argv
     *
     * @return array{0: list<string>, 1: array<string, string|true>}
     */
    private static function parse(array $argv): array
    {
        $args = [];
        $opts = [];
        foreach ($argv as $arg) {
            if (preg_match('/^--?([\w-]+)(?:=(.*))?$/', $arg, $m) === 1) {
                $opts[$m[1]] = $m[2] ?? true;
            } else {
                $args[] = $arg;
            }
        }

        return [$args, $opts];
    }
}
