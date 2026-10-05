<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Container\PortMapping;
use Aybarsm\Podman\Api\Dto\Pod\PodCreateSpec;
use Aybarsm\Podman\Api\Dto\Pod\PodPruneReport;
use Aybarsm\Podman\Api\Dto\Pod\PodSummary;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Enums\PodStatus;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;

it('lists pods with filters and hydrates summaries', function (): void {
    $mock = mockPodman()->fixture('pods/list.json');

    $pods = $mock->client()->pods()->list(Filters::of(['label' => 'app=web']));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/pods/json?filters={"label":["app=web"]}')
        ->and($pods)->toHaveCount(2)
        ->and($pods[0])->toBeInstanceOf(PodSummary::class);

    [$web, $empty] = $pods;

    expect($web->name)->toBe('web')
        ->and($web->status)->toBe('Running')
        ->and($web->knownStatus())->toBe(PodStatus::Running)
        ->and($web->created?->format('Y-m-d H:i:s.u'))->toBe('2025-06-01 10:20:30.123456')
        ->and($web->containers)->toHaveCount(2)
        ->and($web->containers[1]->name)->toBe('web-app')
        ->and($web->containers[1]->restartCount)->toBe(2)
        ->and($web->labels)->toBe(['app' => 'web'])
        ->and($web->networks)->toBe(['podman'])
        ->and($empty->containers)->toBe([])
        ->and($empty->labels)->toBe([])
        ->and($empty->knownStatus())->toBe(PodStatus::Created);
});

it('inspects a pod', function (): void {
    $mock = mockPodman()->fixture('pods/inspect.json');

    $pod = $mock->client()->pods()->inspect('web');

    expect($mock->lastTarget())->toBe('/libpod/pods/web/json')
        ->and($pod->id)->toStartWith('9f8e7d6c')
        ->and($pod->knownState())->toBe(PodStatus::Running)
        ->and($pod->createCommand[2])->toBe('create')
        ->and($pod->createInfra)->toBeTrue()
        ->and($pod->infraConfig?->portBindings['80/tcp'] ?? null)->toBe([['HostIp' => '', 'HostPort' => '8080']])
        ->and($pod->infraConfig?->networkOptions)->toBe(['podman' => ['isolate=true']])
        ->and($pod->infraConfig?->dnsServer)->toBe([])
        ->and($pod->infraConfig?->pidNs)->toBe('private')
        ->and($pod->sharedNamespaces)->toBe(['ipc', 'net', 'uts'])
        ->and($pod->numContainers)->toBe(2)
        ->and($pod->containers[1]->state)->toBe('running')
        ->and($pod->lockNumber)->toBe(7)
        ->and($pod->memoryLimit)->toBe(536870912)
        ->and($pod->mounts[0]['Destination'] ?? null)->toBe('/data')
        ->and($pod->devices)->toBe([]);
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'no such pod');
    $pods = $mock->client()->pods();

    expect($pods->exists('web'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/pods/web/exists')
        ->and($pods->exists('nope'))->toBeFalse();
});

it('creates a pod from a spec, sending only set fields', function (): void {
    $mock = mockPodman()->fixture('pods/create.json', 201);

    $id = $mock->client()->pods()->create(new PodCreateSpec(
        name: 'web',
        labels: ['app' => 'web'],
        portMappings: [new PortMapping(containerPort: 80, hostPort: 8080)],
        networks: ['app-net' => ['aliases' => ['web']]],
        restartPolicy: 'always',
        extra: ['shm_size' => 67108864],
    ));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/pods/create')
        ->and($mock->lastJsonBody())->toBe([
            'name' => 'web',
            'labels' => ['app' => 'web'],
            'portmappings' => [['container_port' => 80, 'host_port' => 8080]],
            'Networks' => ['app-net' => ['aliases' => ['web']]],
            'restart_policy' => 'always',
            'shm_size' => 67108864,
        ])
        ->and($id)->toStartWith('9f8e7d6c');
});

it('creates a pod with defaults by sending an empty object', function (): void {
    $mock = mockPodman()->fixture('pods/create.json', 201);

    $mock->client()->pods()->create();

    expect((string) $mock->lastRequest()->getBody())->toBe('{}');
});

it('surfaces a name conflict on create', function (): void {
    mockPodman()->error(409, 'pod web already exists')->client()->pods()->create(new PodCreateSpec(name: 'web'));
})->throws(ConflictException::class, 'already exists');

it('removes a pod and reports per-container errors', function (): void {
    $mock = mockPodman()->fixture('pods/remove.json');

    $report = $mock->client()->pods()->remove('web', force: true, timeout: 0);

    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/pods/web?force=true&timeout=0')
        ->and($report->error)->toBeNull()
        ->and(array_values($report->removedContainers))->toBe([null, 'unspecified error (not serialised by Podman)']);
});

it('starts and stops, reporting 304 as no-op', function (): void {
    $mock = mockPodman()
        ->json(['Id' => 'p1', 'Errs' => [], 'RawInput' => 'web'])
        ->noContent(304)
        ->json(['Id' => 'p1', 'Errs' => [], 'RawInput' => 'web'])
        ->noContent(304);
    $pods = $mock->client()->pods();

    expect($pods->start('web'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/pods/web/start')
        ->and($pods->start('web'))->toBeFalse()
        ->and($pods->stop('web', timeout: 5))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/pods/web/stop?t=5')
        ->and($pods->stop('web'))->toBeFalse();
});

it('sends lifecycle commands to the right routes', function (string $method, array $args, string $target): void {
    $mock = mockPodman()->json(['Id' => 'p1', 'Errs' => null]);

    $mock->client()->pods()->{$method}(...$args);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe($target);
})->with([
    'restart' => ['restart', ['web'], '/libpod/pods/web/restart'],
    'kill' => ['kill', ['web', 'SIGTERM'], '/libpod/pods/web/kill?signal=SIGTERM'],
    'pause' => ['pause', ['web'], '/libpod/pods/web/pause'],
    'unpause' => ['unpause', ['web'], '/libpod/pods/web/unpause'],
]);

it('surfaces partial state-change failures as conflicts', function (): void {
    mockPodman()->json(['Id' => 'p1', 'Errs' => ['container x: not running']], 409)->client()->pods()->kill('web');
})->throws(ConflictException::class);

it('lists processes as a single snapshot', function (): void {
    $mock = mockPodman()->fixture('pods/top.json');

    $top = $mock->client()->pods()->top('web', 'aux');

    expect($mock->lastTarget())->toBe('/libpod/pods/web/top?stream=false&ps_args=aux')
        ->and($top->titles[0])->toBe('USER')
        ->and($top->processes)->toHaveCount(2)
        ->and($top->rows()[1]['COMMAND'] ?? null)->toStartWith('nginx: master');
});

it('reads pod stats without streaming', function (): void {
    $mock = mockPodman()->fixture('pods/stats.json');

    $stats = $mock->client()->pods()->stats(['web', 'db']);

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/pods/stats?namesOrIDs=web&namesOrIDs=db')
        ->and($stats)->toHaveCount(1)
        ->and($stats[0]->pod)->toBe('9f8e7d6c5b4a')
        ->and($stats[0]->containerId)->toBe('b2c3d4e5f607')
        ->and($stats[0]->cpu)->toBe('0.12%')
        ->and($stats[0]->memUsage)->toBe('2.13MB / 8.31GB')
        ->and($stats[0]->pids)->toBe('3');
});

it('reads stats for all pods', function (): void {
    $mock = mockPodman()->json([]);

    expect($mock->client()->pods()->stats(all: true))->toBe([])
        ->and($mock->lastTarget())->toBe('/libpod/pods/stats?all=true');
});

it('prunes pods from the list Podman returns', function (): void {
    $mock = mockPodman()->fixture('pods/prune.json');

    $reports = $mock->client()->pods()->prune();

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/pods/prune')
        ->and($reports)->toHaveCount(2)
        ->and($reports[0])->toBeInstanceOf(PodPruneReport::class)
        ->and($reports[0]->error)->toBeNull()
        ->and($reports[1]->error)->toBe('unspecified error (not serialised by Podman)');
});

it('also accepts the single-object prune shape the spec documents', function (): void {
    $reports = mockPodman()->json(['Id' => 'p1', 'Err' => null])->client()->pods()->prune();

    expect($reports)->toHaveCount(1)
        ->and($reports[0]->id)->toBe('p1');
});

it('throws NotFoundException for unknown pods', function (): void {
    mockPodman()->error(404, 'no such pod')->client()->pods()->inspect('nope');
})->throws(NotFoundException::class, 'no such pod');
