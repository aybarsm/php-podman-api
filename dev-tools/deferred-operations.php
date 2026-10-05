<?php

declare(strict_types=1);

/*
 * Libpod operations intentionally NOT implemented yet (see CLAUDE.md → Scope).
 * `bin/spec coverage` reports these as "deferred" instead of "missing".
 *
 * Key: Operation case name. Value: reason.
 */
return [
    'ContainerChanges' => 'spec documents no response body (200: no body)',
    'ContainerCheckpoint' => 'spec documents no response body; real body is a JSON report or tar.gz depending on export',
    'ContainerRestore' => 'spec documents no response body; real body is a JSON report',
    'ImageChanges' => 'spec documents no response body (200: no body)',
    'ImageResolve' => 'spec documents no response body (204: no body); real body is a JSON list of resolved names',
    'PlayKubeDown' => 'spec documents no request body, but the route needs the Kubernetes YAML to tear down',
    'ContainerAttach' => 'streaming: hijacked connection (HTTP 101 upgrade)',
    'ContainerLogs' => 'streaming: multiplexed stdout/stderr frames, follow mode',
    'ContainerStats' => 'streaming: NDJSON when stream=true',
    'ContainersStatsAll' => 'streaming: NDJSON when stream=true',
    'ExecStart' => 'streaming: hijacked connection for attached exec sessions',
    'ImageBuild' => 'streaming: tar context upload + NDJSON progress',
    'LocalBuild' => 'streaming: NDJSON build progress',
    'SystemEvents' => 'streaming: NDJSON event stream',
];
