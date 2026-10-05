<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

use RuntimeException;

/**
 * Discovers and loads every resources/podman/swagger-v{version}.yaml file.
 */
final class SpecRepository
{
    private const string PATTERN = '/^swagger-v(\d+\.\d+)\.yaml$/';

    /** @var array<string, Spec> */
    private array $loaded = [];

    public function __construct(private readonly string $directory) {}

    /**
     * @return list<string> versions sorted ascending ('5.4', '5.5', …)
     */
    public function versions(): array
    {
        $versions = [];
        foreach (scandir($this->directory) ?: [] as $file) {
            if (preg_match(self::PATTERN, $file, $m) === 1) {
                $versions[] = $m[1];
            }
        }
        usort($versions, static fn (string $a, string $b): int => version_compare($a, $b));

        return $versions;
    }

    public function latestVersion(): string
    {
        $versions = $this->versions();

        return $versions[array_key_last($versions) ?? throw new RuntimeException("No specs found in {$this->directory}")];
    }

    public function get(string $version): Spec
    {
        if (! in_array($version, $this->versions(), true)) {
            throw new RuntimeException(sprintf(
                'Unknown spec version "%s". Available: %s',
                $version,
                implode(', ', $this->versions()),
            ));
        }

        return $this->loaded[$version] ??= Spec::fromFile($version, $this->path($version));
    }

    public function latest(): Spec
    {
        return $this->get($this->latestVersion());
    }

    /**
     * @return array<string, Spec> version => spec, ascending
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->versions() as $v) {
            $out[$v] = $this->get($v);
        }

        return $out;
    }

    public function path(string $version): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'swagger-v'.$version.'.yaml';
    }
}
