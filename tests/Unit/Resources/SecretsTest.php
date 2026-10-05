<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Secret\SecretCreateOptions;
use Aybarsm\Podman\Api\Dto\Secret\SecretInfo;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;

it('lists secrets with filters', function (): void {
    $mock = mockPodman()->fixture('secrets/list.json');

    $secrets = $mock->client()->secrets()->list(Filters::of(['name' => 'db-.*']));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/secrets/json?filters={"name":["db-.*"]}')
        ->and($secrets)->toHaveCount(2)
        ->and($secrets[0])->toBeInstanceOf(SecretInfo::class)
        ->and($secrets[0]->name())->toBe('db-password')
        ->and($secrets[0]->secretData)->toBeNull()
        ->and($secrets[1]->spec?->driver?->name)->toBe('pass')
        ->and($secrets[1]->spec?->driver?->options)->toBe([])
        ->and($secrets[1]->spec?->labels)->toBe([]);
});

it('inspects a secret, optionally revealing its value', function (): void {
    $mock = mockPodman()->fixture('secrets/inspect.json');

    $secret = $mock->client()->secrets()->inspect('db-password', showSecret: true);

    expect($mock->lastTarget())->toBe('/libpod/secrets/db-password/json?showsecret=true')
        ->and($secret->id)->toBe('a1b2c3d4e5f6a7b8c9d0e1f2a')
        ->and($secret->secretData)->toBe('hunter2')
        ->and($secret->createdAt?->format('Y-m-d H:i:s'))->toBe('2025-06-01 10:20:30')
        ->and($secret->spec?->driver?->options)->toBe(['path' => '/var/lib/containers/storage/secrets/filedriver'])
        ->and($secret->spec?->labels)->toBe(['app' => 'db']);
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'no such secret');
    $secrets = $mock->client()->secrets();

    expect($secrets->exists('db-password'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/secrets/db-password/exists')
        ->and($secrets->exists('nope'))->toBeFalse();
});

it('creates a secret from a raw value with JSON-encoded options', function (): void {
    $mock = mockPodman()->fixture('secrets/create.json', 201);

    $id = $mock->client()->secrets()->create('db-password', 'hunter2', new SecretCreateOptions(
        driver: 'file',
        driverOptions: ['path' => '/run/secrets'],
        labels: ['app' => 'db'],
        replace: true,
    ));

    expect($id)->toBe('a1b2c3d4e5f6a7b8c9d0e1f2a')
        ->and($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/secrets/create?name=db-password&driver=file&driveropts={"path":"/run/secrets"}&labels={"app":"db"}&replace=true')
        ->and((string) $mock->lastRequest()->getBody())->toBe('hunter2');
});

it('removes a secret', function (): void {
    $mock = mockPodman()->noContent()->noContent();
    $secrets = $mock->client()->secrets();

    $secrets->remove('db-password');
    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/secrets/db-password');

    $secrets->remove('db-password', all: true);
    expect($mock->lastTarget())->toBe('/libpod/secrets/db-password?all=true');
});

it('throws NotFoundException for unknown secrets', function (): void {
    mockPodman()->error(404, 'no such secret')->client()->secrets()->inspect('nope');
})->throws(NotFoundException::class, 'no such secret');
