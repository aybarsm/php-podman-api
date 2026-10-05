<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\System\ComponentVersion;
use Aybarsm\Podman\Api\Dto\System\StorageCheckOptions;
use Aybarsm\Podman\Api\Dto\System\SystemPruneOptions;
use Aybarsm\Podman\Api\Exceptions\ServerException;
use GuzzleHttp\Psr7\Response;

it('pings the unversioned endpoint and reads protocol headers', function (): void {
    $mock = mockPodman()->queue(new Response(200, [
        'API-Version' => '1.41',
        'Libpod-API-Version' => '5.5.0',
        'Libpod-Buildah-Version' => '1.40.1',
        'Docker-Experimental' => 'true',
    ], 'OK'));

    $ping = $mock->client()->system()->ping();

    expect($mock->lastRequest()->getUri()->getPath())->toBe('/libpod/_ping')
        ->and($ping->apiVersion)->toBe('1.41')
        ->and($ping->libpodApiVersion)->toBe('5.5.0')
        ->and($ping->buildahVersion)->toBe('1.40.1')
        ->and($ping->experimental)->toBeTrue()
        ->and($ping->isPodman())->toBeTrue();
});

it('reads system info', function (): void {
    $mock = mockPodman()->fixture('system/info.json');

    $info = $mock->client()->system()->info();

    expect($mock->lastTarget())->toBe('/libpod/info')
        ->and($info->host->hostname)->toBe('localhost.localdomain')
        ->and($info->host->cpus)->toBe(4)
        ->and($info->host->cgroupControllers)->toBe(['cpu', 'io', 'memory', 'pids'])
        ->and($info->host->isRootless())->toBeFalse()
        ->and($info->host->distribution['distribution'] ?? null)->toBe('fedora')
        ->and($info->store->graphDriverName)->toBe('overlay')
        ->and($info->store->graphStatus['Supports d_type'] ?? null)->toBe('true')
        ->and($info->version->version)->toBe('5.5.0')
        ->and($info->version->built?->getTimestamp())->toBe(1748304000)
        ->and($info->registries)->toBe(['search' => ['docker.io']]);
});

it('reads component versions', function (): void {
    $mock = mockPodman()->fixture('system/version.json');

    $version = $mock->client()->system()->version();

    expect($mock->lastTarget())->toBe('/libpod/version')
        ->and($version->version)->toBe('5.5.0')
        ->and($version->platformName)->toBe('linux/arm64/fedora-42')
        ->and($version->components)->toHaveCount(2)
        ->and($version->components[0])->toBeInstanceOf(ComponentVersion::class)
        ->and($version->components[0]->details['MinAPIVersion'] ?? null)->toBe('4.0.0')
        ->and($version->experimental)->toBeFalse();
});

it('reads disk usage', function (): void {
    $mock = mockPodman()->fixture('system/df.json');

    $df = $mock->client()->system()->diskUsage();

    expect($mock->lastTarget())->toBe('/libpod/system/df')
        ->and($df->imagesSize)->toBe(13467648)
        ->and($df->images[0]->repository)->toBe('docker.io/library/alpine')
        ->and($df->containers[0]->names)->toBe('web')
        ->and($df->containers[0]->created?->format('u'))->toBe('123456')
        ->and($df->volumes[0]->volumeName)->toBe('data');
});

it('prunes with options and tolerates Go error structs and nil lists', function (): void {
    $mock = mockPodman()->fixture('system/prune.json');

    $report = $mock->client()->system()->prune(new SystemPruneOptions(all: true, volumes: true, filters: Filters::of(['until' => '24h'])));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/system/prune?all=true&volumes=true&filters={"until":["24h"]}')
        ->and($report->pods)->toBe([])
        ->and($report->containers[0]->id)->toBe('6c5d4b3a2f1e')
        ->and($report->images[0]->error)->toBe('unspecified error (not serialised by Podman)')
        ->and($report->networks[0]->name)->toBe('old-net')
        ->and($report->volumes)->toBe([])
        ->and($report->reclaimedSpace)->toBe(8464896);
});

it('runs a storage check with snake_case query parameters', function (): void {
    $mock = mockPodman()->fixture('system/check.json');

    $report = $mock->client()->system()->check(new StorageCheckOptions(repairLossy: true, unreferencedLayerMaxAge: '1h'));

    expect($mock->lastTarget())->toBe('/libpod/system/check?repair_lossy=true&unreferenced_layer_max_age=1h')
        ->and($report->errors)->toBeTrue()
        ->and($report->layers)->toBe(['a1b2c3' => ['layer content incorrect digest']])
        ->and($report->roLayers)->toBe([])
        ->and($report->removedImages)->toBe(['9234e8fb04c4' => ['docker.io/library/alpine:latest']])
        ->and($report->removedContainers)->toBe(['6c5d4b3a2f1e' => 'web']);
});

it('surfaces server errors', function (): void {
    mockPodman()->error(500, 'storage is corrupt')->client()->system()->info();
})->throws(ServerException::class, 'storage is corrupt');
