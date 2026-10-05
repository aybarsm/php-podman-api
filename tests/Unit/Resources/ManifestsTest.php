<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Manifest\ManifestAddRequest;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestDescriptor;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestModifyRequest;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestPushOptions;
use Aybarsm\Podman\Api\Enums\ManifestOperation;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;

const MANIFESTS_TEST_LIST_ID = '8f2e1d0c9b8a7f6e5d4c3b2a1f0e9d8c7b6a5f4e3d2c1b0a9f8e7d6c5b4a3f2e';

it('creates a manifest list', function (): void {
    $mock = mockPodman()->fixture('manifests/create.json', 201);

    $id = $mock->client()->manifests()->create(
        'localhost/mylist:latest',
        ['quay.io/libpod/alpine:latest', 'quay.io/libpod/busybox:latest'],
        all: true,
        options: new ManifestModifyRequest(indexAnnotations: ['org.example' => 'yes']),
    );

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/manifests/localhost%2Fmylist%3Alatest?images=quay.io/libpod/alpine:latest&images=quay.io/libpod/busybox:latest&all=true')
        ->and($mock->lastJsonBody())->toBe(['index_annotations' => ['org.example' => 'yes']])
        ->and($id)->toBe(MANIFESTS_TEST_LIST_ID);
});

it('creates a manifest list without a body', function (): void {
    $mock = mockPodman()->fixture('manifests/create.json', 201);

    $mock->client()->manifests()->create('mylist', ['alpine'], amend: true);

    expect($mock->lastTarget())->toBe('/libpod/manifests/mylist?images=alpine&amend=true')
        ->and((string) $mock->lastRequest()->getBody())->toBe('');
});

it('inspects a manifest list', function (): void {
    $mock = mockPodman()->fixture('manifests/inspect.json');

    $list = $mock->client()->manifests()->inspect('mylist', tlsVerify: false);

    expect($mock->lastTarget())->toBe('/libpod/manifests/mylist/json?tlsVerify=false')
        ->and($list->schemaVersion)->toBe(2)
        ->and($list->mediaType)->toBe('application/vnd.oci.image.index.v1+json')
        ->and($list->manifests)->toHaveCount(2)
        ->and($list->manifests[0])->toBeInstanceOf(ManifestDescriptor::class)
        ->and($list->manifests[0]->platform?->architecture)->toBe('amd64')
        ->and($list->manifests[0]->urls)->toBe([])
        ->and($list->manifests[1]->platform?->variant)->toBe('v8')
        ->and($list->manifests[1]->platform?->osVersion)->toBe('')
        ->and($list->manifests[1]->platform?->osFeatures)->toBe([])
        ->and($list->manifests[1]->size)->toBe(528);
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'manifest not known');
    $manifests = $mock->client()->manifests();

    expect($manifests->exists('mylist'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/manifests/mylist/exists')
        ->and($manifests->exists('nope'))->toBeFalse();
});

it('modifies a manifest list', function (): void {
    $mock = mockPodman()->fixture('manifests/modify.json');

    $report = $mock->client()->manifests()->modify(
        'mylist',
        new ManifestModifyRequest(
            operation: ManifestOperation::Update,
            images: ['quay.io/libpod/alpine:latest'],
            arch: 'arm64',
            variant: 'v8',
            extra: ['annotation' => ['a=b']],
        ),
        tlsVerify: false,
    );

    expect($mock->lastRequest()->getMethod())->toBe('PUT')
        ->and($mock->lastTarget())->toBe('/libpod/manifests/mylist?tlsVerify=false')
        ->and($mock->lastJsonBody())->toBe([
            'operation' => 'update',
            'images' => ['quay.io/libpod/alpine:latest'],
            'arch' => 'arm64',
            'variant' => 'v8',
            'annotation' => ['a=b'],
        ])
        ->and($report->id)->toBe(MANIFESTS_TEST_LIST_ID)
        ->and($report->images)->toHaveCount(1)
        ->and($report->files)->toBe([])
        ->and($report->hasErrors())->toBeFalse();
});

it('surfaces a partial modify failure as a conflict', function (): void {
    mockPodman()->error(409, 'adding image failed')
        ->client()->manifests()->modify('mylist', new ManifestModifyRequest(ManifestOperation::Remove, images: ['sha256:abc']));
})->throws(ConflictException::class);

it('adds an image through the deprecated endpoint', function (): void {
    $mock = mockPodman()->fixture('manifests/create.json');

    $id = $mock->client()->manifests()->add('mylist', new ManifestAddRequest(['alpine'], all: true, os: 'linux'));

    expect($mock->lastTarget())->toBe('/libpod/manifests/mylist/add')
        ->and($mock->lastJsonBody())->toBe(['images' => ['alpine'], 'all' => true, 'os' => 'linux'])
        ->and($id)->toBe(MANIFESTS_TEST_LIST_ID);
});

it('removes a manifest list', function (): void {
    $mock = mockPodman()->fixture('manifests/remove.json');

    $report = $mock->client()->manifests()->remove('mylist', ignore: true);

    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/manifests/mylist?ignore=true')
        ->and($report->deleted)->toBe([MANIFESTS_TEST_LIST_ID])
        ->and($report->untagged)->toBe(['localhost/mylist:latest']);
});

it('pushes a manifest list to a registry', function (): void {
    $mock = mockPodman()->fixture('manifests/push.json');

    $id = $mock->client()->manifests()->push(
        'mylist',
        'quay.io/me/app:latest',
        new ManifestPushOptions(all: true, addCompression: ['zstd', 'gzip'], tlsVerify: false),
    );

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/manifests/mylist/registry/quay.io%2Fme%2Fapp%3Alatest?addCompression=zstd&addCompression=gzip&all=true&tlsVerify=false')
        ->and($id)->toStartWith('sha256:4b3a2f1e');
});

it('pushes through the deprecated v3 endpoint', function (): void {
    $mock = mockPodman()->fixture('manifests/push.json');

    $id = $mock->client()->manifests()->pushV3('mylist', 'quay.io/me/app:latest', all: true);

    expect($mock->lastTarget())->toBe('/libpod/manifests/mylist/push?destination=quay.io/me/app:latest&all=true')
        ->and($id)->toStartWith('sha256:');
});

it('throws NotFoundException for unknown manifest lists', function (): void {
    mockPodman()->error(404, 'manifest not known')->client()->manifests()->inspect('nope');
})->throws(NotFoundException::class, 'manifest not known');
