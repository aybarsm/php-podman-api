<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

/**
 * Structural diff between two spec versions, scoped to the Libpod surface by default.
 */
final readonly class SpecDiff
{
    /**
     * @param list<SpecOperation>          $addedOperations
     * @param list<SpecOperation>          $removedOperations
     * @param array<string, list<string>>  $changedOperations operationId => change lines
     * @param list<string>                 $addedDefinitions
     * @param list<string>                 $removedDefinitions
     * @param array<string, list<string>>  $changedDefinitions definition => change lines
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $addedOperations,
        public array $removedOperations,
        public array $changedOperations,
        public array $addedDefinitions,
        public array $removedDefinitions,
        public array $changedDefinitions,
    ) {}

    public static function compare(Spec $from, Spec $to, bool $libpodOnly = true): self
    {
        $a = $libpodOnly ? $from->libpodOperations() : $from->operations();
        $b = $libpodOnly ? $to->libpodOperations() : $to->operations();

        $changedOps = [];
        foreach (array_intersect_key($a, $b) as $id => $old) {
            $lines = self::operationChanges($old, $b[$id]);
            if ($lines !== []) {
                $changedOps[$id] = $lines;
            }
        }

        $defsA = $libpodOnly ? $from->reachableDefinitions($a) : array_keys($from->definitions());
        $defsB = $libpodOnly ? $to->reachableDefinitions($b) : array_keys($to->definitions());

        $changedDefs = [];
        foreach (array_intersect($defsA, $defsB) as $name) {
            $lines = self::definitionChanges($from->definition($name) ?? [], $to->definition($name) ?? []);
            if ($lines !== []) {
                $changedDefs[$name] = $lines;
            }
        }

        return new self(
            from: $from->version,
            to: $to->version,
            addedOperations: array_values(array_diff_key($b, $a)),
            removedOperations: array_values(array_diff_key($a, $b)),
            changedOperations: $changedOps,
            addedDefinitions: array_values(array_diff($defsB, $defsA)),
            removedDefinitions: array_values(array_diff($defsA, $defsB)),
            changedDefinitions: $changedDefs,
        );
    }

    public function isEmpty(): bool
    {
        return $this->addedOperations === []
            && $this->removedOperations === []
            && $this->changedOperations === []
            && $this->addedDefinitions === []
            && $this->removedDefinitions === []
            && $this->changedDefinitions === [];
    }

    public function render(): string
    {
        $out = ["Spec diff v{$this->from} → v{$this->to}", ''];

        $opLine = static fn (SpecOperation $op): string => sprintf('%-6s %-48s %s', $op->method, $op->path, $op->id);

        $out = [
            ...$out,
            ...self::section('Added operations', array_map($opLine, $this->addedOperations)),
            ...self::section('Removed operations', array_map($opLine, $this->removedOperations)),
            ...self::section('Changed operations', self::nested($this->changedOperations), count($this->changedOperations)),
            ...self::section('Added definitions', $this->addedDefinitions),
            ...self::section('Removed definitions', $this->removedDefinitions),
            ...self::section('Changed definitions', self::nested($this->changedDefinitions), count($this->changedDefinitions)),
        ];

        return rtrim(implode(PHP_EOL, $out)).PHP_EOL;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function section(string $title, array $lines, ?int $count = null): array
    {
        return [
            sprintf('## %s (%d)', $title, $count ?? count($lines)),
            ...array_map(static fn (string $line): string => '  '.$line, $lines),
            '',
        ];
    }

    /**
     * @param array<string, list<string>> $groups
     *
     * @return list<string>
     */
    private static function nested(array $groups): array
    {
        $out = [];
        foreach ($groups as $name => $lines) {
            $out[] = $name;
            foreach ($lines as $line) {
                $out[] = '    '.$line;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function operationChanges(SpecOperation $old, SpecOperation $new): array
    {
        $lines = [];
        if ($old->method !== $new->method || $old->path !== $new->path) {
            $lines[] = "route: {$old->method} {$old->path} → {$new->method} {$new->path}";
        }

        $pa = $old->parametersByKey();
        $pb = $new->parametersByKey();
        foreach (array_diff_key($pb, $pa) as $key => $p) {
            $lines[] = "+ param {$key} ({$p->signature()})";
        }
        foreach (array_diff_key($pa, $pb) as $key => $p) {
            $lines[] = "- param {$key} ({$p->signature()})";
        }
        foreach (array_intersect_key($pa, $pb) as $key => $p) {
            if ($p->signature() !== $pb[$key]->signature()) {
                $lines[] = "~ param {$key}: {$p->signature()} → {$pb[$key]->signature()}";
            }
        }

        foreach (array_diff_key($new->responses, $old->responses) as $code => $desc) {
            $lines[] = "+ response {$code}: {$desc}";
        }
        foreach (array_diff_key($old->responses, $new->responses) as $code => $desc) {
            $lines[] = "- response {$code}: {$desc}";
        }
        foreach (array_intersect_key($old->responses, $new->responses) as $code => $desc) {
            if ($desc !== $new->responses[$code]) {
                $lines[] = "~ response {$code}: {$desc} → {$new->responses[$code]}";
            }
        }

        if ($old->produces !== $new->produces) {
            $lines[] = sprintf('~ produces: [%s] → [%s]', implode(', ', $old->produces), implode(', ', $new->produces));
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return list<string>
     */
    private static function definitionChanges(array $old, array $new): array
    {
        $lines = [];
        $ta = SchemaRef::describe(array_diff_key($old, ['properties' => 1]));
        $tb = SchemaRef::describe(array_diff_key($new, ['properties' => 1]));
        if ($ta !== $tb) {
            $lines[] = "~ type: {$ta} → {$tb}";
        }

        $pa = array_map(static fn (mixed $p): string => SchemaRef::describe(Node::map($p)) ?? 'mixed', Node::map($old['properties'] ?? null));
        $pb = array_map(static fn (mixed $p): string => SchemaRef::describe(Node::map($p)) ?? 'mixed', Node::map($new['properties'] ?? null));
        ksort($pa);
        ksort($pb);

        foreach (array_diff_key($pb, $pa) as $name => $type) {
            $lines[] = "+ {$name}: {$type}";
        }
        foreach (array_diff_key($pa, $pb) as $name => $type) {
            $lines[] = "- {$name}: {$type}";
        }
        foreach (array_intersect_key($pa, $pb) as $name => $type) {
            if ($type !== $pb[$name]) {
                $lines[] = "~ {$name}: {$type} → {$pb[$name]}";
            }
        }

        $ra = Node::strings($old['required'] ?? null);
        $rb = Node::strings($new['required'] ?? null);
        sort($ra);
        sort($rb);
        if ($ra !== $rb) {
            $lines[] = sprintf('~ required: [%s] → [%s]', implode(', ', $ra), implode(', ', $rb));
        }

        return $lines;
    }
}
