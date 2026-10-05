<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Volume\VolumeCreateRequest;
use Aybarsm\Podman\Api\Dto\Volume\VolumeInspect;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;
use GuzzleHttp\Psr7\Response;

it('lists volumes with filters', function (): void {
    $mock = mockPodman()->fixture('volumes/list.json');

    $volumes = $mock->client()->volumes()->list(Filters::of(['driver' => 'local']));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/volumes/json?filters={"driver":["local"]}')
        ->and($volumes)->toHaveCount(2)
        ->and($volumes[0])->toBeInstanceOf(VolumeInspect::class)
        ->and($volumes[0]->name)->toBe('data')
        ->and($volumes[0]->labels)->toBe(['app' => 'web'])
        ->and($volumes[0]->options)->toBe([])
        ->and($volumes[1]->anonymous)->toBeTrue()
        ->and($volumes[1]->labels)->toBe([])
        ->and($volumes[1]->options)->toBe(['type' => 'tmpfs', 'device' => 'tmpfs', 'o' => 'size=100m']);
});

it('lists volumes without filters', function (): void {
    $mock = mockPodman()->json(null);

    expect($mock->client()->volumes()->list())->toBe([])
        ->and($mock->lastTarget())->toBe('/libpod/volumes/json');
});

it('inspects a volume', function (): void {
    $mock = mockPodman()->fixture('volumes/inspect.json');

    $volume = $mock->client()->volumes()->inspect('data');

    expect($mock->lastTarget())->toBe('/libpod/volumes/data/json')
        ->and($volume->name)->toBe('data')
        ->and($volume->driver)->toBe('local')
        ->and($volume->mountpoint)->toBe('/var/lib/containers/storage/volumes/data/_data')
        ->and($volume->createdAt?->format('Y-m-d H:i:s.u'))->toBe('2025-06-01 10:20:30.123456')
        ->and($volume->mountCount)->toBe(1)
        ->and($volume->needsChown)->toBeTrue()
        ->and($volume->uid)->toBe(1000)
        ->and($volume->lockNumber)->toBe(12)
        ->and($volume->status)->toBe([])
        ->and($volume->storageId)->toBeNull();
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'no such volume');
    $volumes = $mock->client()->volumes();

    expect($volumes->exists('data'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/volumes/data/exists')
        ->and($volumes->exists('nope'))->toBeFalse();
});

it('creates a volume, sending only set fields', function (): void {
    $mock = mockPodman()->fixture('volumes/inspect.json', 201);

    $volume = $mock->client()->volumes()->create(new VolumeCreateRequest(
        name: 'data',
        labels: ['app' => 'web'],
        options: ['o' => 'uid=1000'],
        ignoreIfExists: true,
    ));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/volumes/create')
        ->and($mock->lastJsonBody())->toBe([
            'Name' => 'data',
            'Labels' => ['app' => 'web'],
            'Options' => ['o' => 'uid=1000'],
            'IgnoreIfExists' => true,
        ])
        ->and($volume->name)->toBe('data');
});

it('creates an unnamed volume with an empty JSON object', function (): void {
    $mock = mockPodman()->fixture('volumes/inspect.json', 201);

    $mock->client()->volumes()->create();

    expect((string) $mock->lastRequest()->getBody())->toBe('{}');
});

it('removes a volume', function (): void {
    $mock = mockPodman()->noContent()->noContent();
    $volumes = $mock->client()->volumes();

    $volumes->remove('data');
    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/volumes/data');

    $volumes->remove('data', force: true, timeout: 5);
    expect($mock->lastTarget())->toBe('/libpod/volumes/data?force=true&timeout=5');
});

it('surfaces a volume in use on remove', function (): void {
    mockPodman()->error(409, 'volume is being used')->client()->volumes()->remove('data');
})->throws(ConflictException::class, 'volume is being used');

it('prunes volumes', function (): void {
    $mock = mockPodman()->fixture('volumes/prune.json');

    $reports = $mock->client()->volumes()->prune(Filters::of(['label' => 'tmp']), dryRun: true);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/volumes/prune?filters={"label":["tmp"]}&dryrun=true')
        ->and($reports)->toHaveCount(2)
        ->and($reports[0]->id)->toBe('old-cache')
        ->and($reports[0]->size)->toBe(4096)
        ->and($reports[1]->error)->toBe('volume is being used');
});

it('exports a volume as tar', function (): void {
    $mock = mockPodman()->queue(new Response(200, ['Content-Type' => 'application/x-tar'], 'TAR'));

    expect((string) $mock->client()->volumes()->export('my vol'))->toBe('TAR')
        ->and($mock->lastRequest()->getUri()->getPath())->toEndWith('/libpod/volumes/my%20vol/export');
});

it('imports a tar archive into a volume', function (): void {
    $mock = mockPodman()->noContent();

    $mock->client()->volumes()->import('data', 'TARBYTES');

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/volumes/data/import')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/x-tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('TARBYTES');
});

it('gates volume export and import behind Podman 5.6', function (string $method, array $args): void {
    MockPodman::withVersion(ApiVersion::V5_4)->client()->volumes()->{$method}(...$args);
})->with([
    'export' => ['export', ['data']],
    'import' => ['import', ['data', 'TAR']],
])->throws(UnsupportedApiVersionException::class);

it('throws NotFoundException for unknown volumes', function (): void {
    mockPodman()->error(404, 'no such volume')->client()->volumes()->inspect('nope');
})->throws(NotFoundException::class, 'no such volume');
