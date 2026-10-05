<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dev\Spec;

/**
 * Computes the first spec version in which each Libpod operation appears.
 */
final class SinceCalculator
{
    /**
     * @param array<string, Spec> $specs version => spec, ascending
     *
     * @return array<string, string> operationId => earliest version (only for operations in the newest spec)
     */
    public static function compute(array $specs): array
    {
        if ($specs === []) {
            return [];
        }

        $latest = $specs[array_key_last($specs)];
        $since = [];

        foreach (array_keys($latest->libpodOperations()) as $id) {
            // Walk backwards so an operation removed and re-added counts from its latest reintroduction.
            $first = $latest->version;
            foreach (array_reverse($specs, true) as $version => $spec) {
                if (! array_key_exists($id, $spec->libpodOperations())) {
                    break;
                }
                $first = (string) $version;
            }
            $since[$id] = $first;
        }

        return $since;
    }
}
