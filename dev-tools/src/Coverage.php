<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev;

use Aybarsm\Podman\Api\Dev\Spec\Spec;
use Aybarsm\Podman\Api\Dev\Spec\SpecOperation;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Compares Operation cases referenced from src/Resources against every Libpod operation in the spec.
 */
final readonly class Coverage
{
    /**
     * @param array<string, SpecOperation> $implemented caseName => op
     * @param array<string, SpecOperation> $deferred    caseName => op
     * @param array<string, SpecOperation> $missing     caseName => op
     * @param array<string, string>        $reasons     caseName => deferral reason
     */
    public function __construct(
        public array $implemented,
        public array $deferred,
        public array $missing,
        public array $reasons,
    ) {}

    /**
     * @param array<string, string> $deferredReasons caseName => why it is intentionally not implemented
     */
    public static function compute(Spec $spec, string $resourcesDir, array $deferredReasons): self
    {
        $used = self::referencedCases($resourcesDir);

        $implemented = $deferred = $missing = [];
        foreach ($spec->libpodOperations() as $op) {
            $case = $op->caseName();
            match (true) {
                isset($used[$case]) => $implemented[$case] = $op,
                isset($deferredReasons[$case]) => $deferred[$case] = $op,
                default => $missing[$case] = $op,
            };
        }

        return new self($implemented, $deferred, $missing, $deferredReasons);
    }

    public function total(): int
    {
        return count($this->implemented) + count($this->deferred) + count($this->missing);
    }

    public function render(bool $verbose = false): string
    {
        $total = $this->total();
        $out = [sprintf(
            'Libpod coverage: %d/%d implemented (%.1f%%), %d deferred, %d missing',
            count($this->implemented),
            $total,
            $total > 0 ? count($this->implemented) / $total * 100 : 0,
            count($this->deferred),
            count($this->missing),
        ), ''];

        $byTag = [];
        foreach (['implemented' => $this->implemented, 'deferred' => $this->deferred, 'missing' => $this->missing] as $state => $ops) {
            foreach ($ops as $case => $op) {
                $byTag[$op->primaryTag()][$state][] = $case;
            }
        }
        ksort($byTag);

        foreach ($byTag as $tag => $states) {
            $out[] = sprintf(
                '%-12s %3d implemented  %3d deferred  %3d missing',
                $tag,
                count($states['implemented'] ?? []),
                count($states['deferred'] ?? []),
                count($states['missing'] ?? []),
            );
            if ($verbose) {
                foreach ($states['missing'] ?? [] as $case) {
                    $out[] = "    missing  {$case}";
                }
                foreach ($states['deferred'] ?? [] as $case) {
                    $out[] = "    deferred {$case}: ".($this->reasons[$case] ?? '');
                }
            }
        }

        return implode(PHP_EOL, $out).PHP_EOL;
    }

    /**
     * @return array<string, true>
     */
    private static function referencedCases(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $used = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/\bOperation::([A-Z]\w*)\b/', (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $case) {
                $used[$case] = true;
            }
        }

        return $used;
    }
}
