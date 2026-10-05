<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Container\ContainerCommitOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerCreateSpec;
use Aybarsm\Podman\Api\Dto\Container\ContainerListOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerRemoveOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerSummary;
use Aybarsm\Podman\Api\Dto\Container\ContainerUpdateRequest;
use Aybarsm\Podman\Api\Dto\Container\PortMapping;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Enums\ContainerState;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;
use GuzzleHttp\Psr7\Response;

it('lists containers with options and hydrates summaries', function (): void {
    $mock = mockPodman()->fixture('containers/list.json');

    $containers = $mock->client()->containers()->list(new ContainerListOptions(
        all: true,
        size: true,
        filters: Filters::of(['label' => 'app=web']),
    ));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/containers/json?all=true&size=true&filters={"label":["app=web"]}')
        ->and($containers)->toHaveCount(2)
        ->and($containers[0])->toBeInstanceOf(ContainerSummary::class);

    [$web, $oneshot] = $containers;

    expect($web->name())->toBe('web')
        ->and($web->knownState())->toBe(ContainerState::Running)
        ->and($web->ports[0]->hostPort)->toBe(8080)
        ->and($web->labels)->toBe(['app' => 'web'])
        ->and($web->sizeRootFs)->toBe(192000000)
        ->and($web->exitedAt)->toBeNull()
        ->and($web->startedAt?->getTimestamp())->toBe(1748773230)
        ->and($oneshot->command)->toBe([])
        ->and($oneshot->labels)->toBe([])
        ->and($oneshot->knownState())->toBeNull()
        ->and($oneshot->state)->toBe('configured')
        ->and($oneshot->exitCode)->toBe(137);
});

it('inspects a container', function (): void {
    $mock = mockPodman()->fixture('containers/inspect.json');

    $c = $mock->client()->containers()->inspect('web', size: true);

    expect($mock->lastTarget())->toBe('/libpod/containers/web/json?size=true')
        ->and($c->name)->toBe('web')
        ->and($c->state->running)->toBeTrue()
        ->and($c->state->knownState())->toBe(ContainerState::Running)
        ->and($c->state->finishedAt)->toBeNull()
        ->and($c->state->startedAt?->format('Y-m-d H:i:s.u'))->toBe('2025-06-01 10:20:31.500000')
        ->and($c->state->health?->isHealthy())->toBeTrue()
        ->and($c->env())->toBe(['PATH' => '/usr/local/sbin:/usr/local/bin', 'NGINX_VERSION' => '1.27.5', 'EMPTY' => ''])
        ->and($c->labels())->toBe(['app' => 'web'])
        ->and($c->mounts[0]['Destination'] ?? null)->toBe('/usr/share/nginx/html')
        ->and($c->effectiveCaps)->toContain('CAP_CHOWN')
        ->and($c->lockNumber)->toBe(3);
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'no such container');
    $containers = $mock->client()->containers();

    expect($containers->exists('web'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/containers/web/exists')
        ->and($containers->exists('nope'))->toBeFalse();
});

it('creates a container from a spec, sending only set fields', function (): void {
    $mock = mockPodman()->fixture('containers/create.json', 201);

    $result = $mock->client()->containers()->create(new ContainerCreateSpec(
        image: 'nginx:latest',
        name: 'web',
        env: ['A' => '1'],
        portMappings: [new PortMapping(containerPort: 80, hostPort: 8080)],
        restartPolicy: 'always',
        extra: ['timezone' => 'UTC'],
    ));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/containers/create')
        ->and($mock->lastJsonBody())->toBe([
            'image' => 'nginx:latest',
            'name' => 'web',
            'env' => ['A' => '1'],
            'restart_policy' => 'always',
            'portmappings' => [['container_port' => 80, 'host_port' => 8080]],
            'timezone' => 'UTC',
        ])
        ->and($result->id)->toStartWith('6c5d4b3a')
        ->and($result->warnings)->toBe([]);
});

it('surfaces a name conflict on create', function (): void {
    mockPodman()->error(409, 'the container name "web" is already in use')
        ->client()->containers()->create(new ContainerCreateSpec(image: 'nginx', name: 'web'));
})->throws(ConflictException::class, 'already in use');

it('removes a container with options', function (): void {
    $mock = mockPodman()->fixture('containers/remove.json');

    $reports = $mock->client()->containers()->remove('web', new ContainerRemoveOptions(force: true, volumes: true, timeout: 0));

    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/containers/web?force=true&timeout=0&v=true')
        ->and($reports[0]->error)->toBeNull();
});

it('treats a 204 remove as an empty report list', function (): void {
    expect(mockPodman()->noContent()->client()->containers()->remove('web'))->toBe([]);
});

it('starts and stops, reporting 304 as no-op', function (): void {
    $mock = mockPodman()->noContent()->noContent(304)->noContent()->noContent(304);
    $containers = $mock->client()->containers();

    expect($containers->start('web'))->toBeTrue()
        ->and($containers->start('web'))->toBeFalse()
        ->and($containers->stop('web', timeout: 5, ignore: true))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/containers/web/stop?timeout=5&ignore=true')
        ->and($containers->stop('web'))->toBeFalse();
});

it('uses the pre-5.8 "Ignore" query key on older servers', function (): void {
    $mock = MockPodman::withVersion(ApiVersion::V5_7)->noContent();

    $mock->client()->containers()->stop('web', ignore: true);

    expect($mock->lastTarget())->toBe('/libpod/containers/web/stop?Ignore=true');
});

it('sends lifecycle commands to the right routes', function (string $method, array $args, string $target): void {
    $mock = mockPodman()->noContent();

    $mock->client()->containers()->{$method}(...$args);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe($target);
})->with([
    'restart' => ['restart', ['web', 3], '/libpod/containers/web/restart?timeout=3'],
    'kill' => ['kill', ['web', 'SIGTERM'], '/libpod/containers/web/kill?signal=SIGTERM'],
    'pause' => ['pause', ['web'], '/libpod/containers/web/pause'],
    'unpause' => ['unpause', ['web'], '/libpod/containers/web/unpause'],
    'init' => ['init', ['web'], '/libpod/containers/web/init'],
    'rename' => ['rename', ['web', 'web2'], '/libpod/containers/web/rename?name=web2'],
    'unmount' => ['unmount', ['web'], '/libpod/containers/web/unmount'],
    'resize' => ['resize', ['web', 40, 120], '/libpod/containers/web/resize?h=40&w=120'],
]);

it('waits for conditions and returns the exit code', function (): void {
    $mock = mockPodman()->json(137);

    $code = $mock->client()->containers()->wait('web', ['exited', 'stopped'], '100ms');

    expect($code)->toBe(137)
        ->and($mock->lastTarget())->toBe('/libpod/containers/web/wait?condition=exited&condition=stopped&interval=100ms');
});

it('lists processes', function (): void {
    $mock = mockPodman()->fixture('containers/top.json');

    $top = $mock->client()->containers()->top('web', ['user', 'pid']);

    expect($mock->lastTarget())->toBe('/libpod/containers/web/top?ps_args=user&ps_args=pid&stream=false')
        ->and($top->titles[0])->toBe('USER')
        ->and($top->processes)->toHaveCount(2)
        ->and($top->rows()[1]['COMMAND'] ?? null)->toBe('nginx: worker process');
});

it('prunes stopped containers', function (): void {
    $mock = mockPodman()->fixture('containers/prune.json');

    $reports = $mock->client()->containers()->prune(Filters::of(['until' => '24h']));

    expect($mock->lastTarget())->toBe('/libpod/containers/prune?filters={"until":["24h"]}')
        ->and($reports)->toHaveCount(2)
        ->and($reports[0]->size)->toBe(1024)
        ->and($reports[1]->error)->toBe('container is running');
});

it('streams exports and archives as tar', function (): void {
    $mock = mockPodman()
        ->queue(new Response(200, ['Content-Type' => 'application/x-tar'], 'TAR1'))
        ->queue(new Response(200, ['Content-Type' => 'application/x-tar'], 'TAR2'));
    $containers = $mock->client()->containers();

    expect((string) $containers->export('web'))->toBe('TAR1')
        ->and($mock->lastTarget())->toBe('/libpod/containers/web/export')
        ->and((string) $containers->getArchive('web', '/etc', ['/etc' => 'config']))->toBe('TAR2')
        ->and($mock->lastTarget())->toBe('/libpod/containers/web/archive?path=/etc&rename={"/etc":"config"}');
});

it('uploads a tar archive into the container', function (): void {
    $mock = mockPodman()->queue(new Response(200));

    $mock->client()->containers()->putArchive('web', '/tmp', 'TARBYTES', pause: false);

    expect($mock->lastRequest()->getMethod())->toBe('PUT')
        ->and($mock->lastTarget())->toBe('/libpod/containers/web/archive?path=/tmp&pause=false')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/x-tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('TARBYTES');
});

it('runs a healthcheck', function (): void {
    $health = mockPodman()->fixture('containers/healthcheck.json')->client()->containers()->healthcheck('web');

    expect($health->isHealthy())->toBeFalse()
        ->and($health->failingStreak)->toBe(3)
        ->and($health->log[0]->output)->toBe('curl: (7) Failed to connect');
});

it('mounts and lists mounted containers', function (): void {
    $mock = mockPodman()->json('/var/lib/containers/storage/overlay/abc/merged')->json(['6c5d' => '/mnt/x']);
    $containers = $mock->client()->containers();

    expect($containers->mount('web'))->toBe('/var/lib/containers/storage/overlay/abc/merged')
        ->and($containers->mounted())->toBe(['6c5d' => '/mnt/x'])
        ->and($mock->lastTarget())->toBe('/libpod/containers/showmounted');
});

it('updates resources and restart policy', function (): void {
    $mock = mockPodman()->json([], 201);

    $mock->client()->containers()->update('web', new ContainerUpdateRequest(env: ['A=2'], memory: ['limit' => 536870912]), 'on-failure', 3);

    expect($mock->lastTarget())->toBe('/libpod/containers/web/update?restartPolicy=on-failure&restartRetries=3')
        ->and($mock->lastJsonBody())->toBe(['Env' => ['A=2'], 'memory' => ['limit' => 536870912]]);
});

it('sends an empty JSON object when updating only the restart policy', function (): void {
    $mock = mockPodman()->json([], 201);

    $mock->client()->containers()->update('web', restartPolicy: 'always');

    expect((string) $mock->lastRequest()->getBody())->toBe('{}');
});

it('commits a container to an image', function (): void {
    $mock = mockPodman()->noContent(201);

    $mock->client()->containers()->commit('web', new ContainerCommitOptions(repo: 'localhost/web', tag: 'v1', changes: ['CMD=/bin/sh']));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/commit?container=web&repo=localhost/web&tag=v1&changes=CMD=/bin/sh');
});

it('throws NotFoundException for unknown containers', function (): void {
    mockPodman()->error(404, 'no such container')->client()->containers()->inspect('nope');
})->throws(NotFoundException::class, 'no such container');
