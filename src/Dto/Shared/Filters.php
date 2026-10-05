<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Shared;

use JsonSerializable;
use Override;

/**
 * Podman's `filters` query parameter: a JSON object of filter name → list of values.
 *
 *     Filters::of(['label' => ['app=web'], 'status' => ['running']])
 *     Filters::empty()->with('label', 'app=web', 'tier=frontend')
 */
final readonly class Filters implements JsonSerializable
{
    /**
     * @param array<string, list<string>> $filters
     */
    private function __construct(public array $filters) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, string|list<string>> $filters
     */
    public static function of(array $filters): self
    {
        $normalised = [];
        foreach ($filters as $name => $values) {
            $normalised[$name] = is_array($values) ? array_values($values) : [$values];
        }

        return new self($normalised);
    }

    public function with(string $name, string ...$values): self
    {
        $filters = $this->filters;
        $filters[$name] = array_values([...($filters[$name] ?? []), ...$values]);

        return new self($filters);
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    /**
     * @return array<string, list<string>>|object
     */
    #[Override]
    public function jsonSerialize(): array|object
    {
        return $this->filters === [] ? (object) [] : $this->filters;
    }
}
