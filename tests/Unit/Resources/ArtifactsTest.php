<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Artifact\ArtifactAddOptions;
use Aybarsm\Podman\Api\Dto\Artifact\ArtifactSummary;
use Aybarsm\Podman\Api\Dto\Shared\RegistryAuth;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\UnauthorizedException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;
use GuzzleHttp\Psr7\Response;

it('lists artifacts', function (): void {
    $mock = mockPodman()->fixture('artifacts/list.json');

    $artifacts = $mock->client()->artifacts()->list();

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/artifacts/json')
        ->and($artifacts)->toHaveCount(2)
        ->and($artifacts[0])->toBeInstanceOf(ArtifactSummary::class)
        ->and($artifacts[0]->name)->toBe('quay.io/acme/config:v1')
        ->and($artifacts[0]->manifest?->artifactType)->toBe('application/vnd.acme.config')
        ->and($artifacts[0]->manifest?->layers[0]->title())->toBe('app.yaml')
        ->and($artifacts[1]->manifest?->layers)->toBe([])
        ->and($artifacts[1]->manifest?->config)->toBeNull();
});

it('inspects an artifact', function (): void {
    $mock = mockPodman()->fixture('artifacts/inspect.json');

    $artifact = $mock->client()->artifacts()->inspect('quay.io/acme/config:v1');

    expect($mock->lastRequest()->getUri()->getPath())->toEndWith('/libpod/artifacts/quay.io%2Facme%2Fconfig%3Av1/json')
        ->and($artifact->name)->toBe('quay.io/acme/config:v1')
        ->and($artifact->digest)->toStartWith('sha256:5b0bcabd')
        ->and($artifact->manifest?->schemaVersion)->toBe(2)
        ->and($artifact->manifest?->config?->size)->toBe(2)
        ->and($artifact->manifest?->layers)->toHaveCount(2)
        ->and($artifact->manifest?->layers[1]->mediaType)->toBe('text/plain')
        ->and($artifact->manifest?->layers[1]->size)->toBe(12)
        ->and($artifact->manifest?->layers[1]->title())->toBe('README.txt')
        ->and($artifact->manifest?->annotations)->toBe(['org.opencontainers.image.created' => '2025-06-01T10:20:30Z']);
});

it('adds a file as an artifact', function (): void {
    $mock = mockPodman()->fixture('artifacts/add.json', 201);

    $digest = $mock->client()->artifacts()->add('quay.io/acme/config:v1', 'app.yaml', 'key: value', new ArtifactAddOptions(
        fileMimeType: 'application/yaml',
        annotations: ['team=infra', 'env=prod'],
        append: true,
    ));

    expect($digest)->toStartWith('sha256:5b0bcabd')
        ->and($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/artifacts/add?name=quay.io/acme/config:v1&fileName=app.yaml&fileMIMEType=application/yaml&annotations=team=infra&annotations=env=prod&append=true')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/octet-stream')
        ->and((string) $mock->lastRequest()->getBody())->toBe('key: value');
});

it('adds a server-local file as an artifact', function (): void {
    $mock = mockPodman()->fixture('artifacts/add.json', 201);

    $digest = $mock->client()->artifacts()->addLocal('quay.io/acme/config:v1', '/srv/app.yaml', 'app.yaml', new ArtifactAddOptions(replace: true));

    expect($digest)->toStartWith('sha256:')
        ->and($mock->lastTarget())->toBe('/libpod/artifacts/local/add?name=quay.io/acme/config:v1&path=/srv/app.yaml&fileName=app.yaml&replace=true')
        ->and((string) $mock->lastRequest()->getBody())->toBe('');
});

it('removes artifacts', function (): void {
    $mock = mockPodman()->fixture('artifacts/remove.json')->fixture('artifacts/remove.json');
    $artifacts = $mock->client()->artifacts();

    expect($artifacts->remove('quay.io/acme/config:v1'))->toHaveCount(2)
        ->and($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastRequest()->getUri()->getPath())->toEndWith('/libpod/artifacts/quay.io%2Facme%2Fconfig%3Av1')
        ->and($artifacts->removeMany(['a:1', 'b:2'], ignore: true)[1])->toStartWith('sha256:2c26')
        ->and($mock->lastTarget())->toBe('/libpod/artifacts/remove?artifacts=a:1&artifacts=b:2&ignore=true');
});

it('extracts artifact files as tar', function (): void {
    $mock = mockPodman()->queue(new Response(200, ['Content-Type' => 'application/x-tar'], 'TAR'));

    $tar = $mock->client()->artifacts()->extract('acme-config', title: 'app.yaml', excludeTitle: true);

    expect((string) $tar)->toBe('TAR')
        ->and($mock->lastTarget())->toBe('/libpod/artifacts/acme-config/extract?title=app.yaml&excludeTitle=true');
});

it('pulls and pushes with registry auth', function (): void {
    $mock = mockPodman()->fixture('artifacts/add.json')->fixture('artifacts/add.json');
    $artifacts = $mock->client()->artifacts();
    $auth = RegistryAuth::credentials('bob', 's3cret', 'quay.io');

    expect($artifacts->pull('quay.io/acme/config:v1', $auth, tlsVerify: false, retry: 1))->toStartWith('sha256:')
        ->and($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/artifacts/pull?name=quay.io/acme/config:v1&retry=1&tlsVerify=false')
        ->and($mock->lastRequest()->getHeaderLine('X-Registry-Auth'))->toBe($auth->toHeader());

    expect($artifacts->push('quay.io/acme/config:v1', retryDelay: '5s'))->toStartWith('sha256:')
        ->and($mock->lastRequest()->getUri()->getPath())->toEndWith('/libpod/artifacts/quay.io%2Facme%2Fconfig%3Av1/push')
        ->and($mock->lastTarget())->toEndWith('/push?retryDelay=5s')
        ->and($mock->lastRequest()->hasHeader('X-Registry-Auth'))->toBeFalse();
});

it('surfaces bad registry credentials', function (): void {
    mockPodman()->error(401, 'unauthorized')->client()->artifacts()->pull('quay.io/acme/config:v1');
})->throws(UnauthorizedException::class);

it('throws NotFoundException for unknown artifacts', function (): void {
    mockPodman()->error(404, 'artifact does not exist')->client()->artifacts()->inspect('nope');
})->throws(NotFoundException::class, 'artifact does not exist');

it('gates artifacts behind Podman 5.6', function (): void {
    MockPodman::withVersion(ApiVersion::V5_4)->client()->artifacts()->list();
})->throws(UnsupportedApiVersionException::class);

it('gates bulk removal behind Podman 5.7 and local add behind 5.8', function (string $method, array $args): void {
    MockPodman::withVersion(ApiVersion::V5_6)->client()->artifacts()->{$method}(...$args);
})->with([
    'removeMany' => ['removeMany', [[], true]],
    'addLocal' => ['addLocal', ['a:1', '/srv/f', 'f']],
])->throws(UnsupportedApiVersionException::class);
