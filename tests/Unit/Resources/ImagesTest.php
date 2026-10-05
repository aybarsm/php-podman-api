<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Image\ImageHistoryEntry;
use Aybarsm\Podman\Api\Dto\Image\ImageImportOptions;
use Aybarsm\Podman\Api\Dto\Image\ImagePruneOptions;
use Aybarsm\Podman\Api\Dto\Image\ImagePullOptions;
use Aybarsm\Podman\Api\Dto\Image\ImagePushOptions;
use Aybarsm\Podman\Api\Dto\Image\ImageRemoveManyOptions;
use Aybarsm\Podman\Api\Dto\Image\ImageSearchOptions;
use Aybarsm\Podman\Api\Dto\Image\ImageSearchResult;
use Aybarsm\Podman\Api\Dto\Image\ImageSummary;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Shared\RegistryAuth;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;
use GuzzleHttp\Psr7\Response;

const IMAGES_TEST_ALPINE_ID = '9c6f0724472873bb50a2ae67a9e7adcb57673a183cea8b06eb778dca859181b5';

it('lists images with filters and hydrates summaries', function (): void {
    $mock = mockPodman()->fixture('images/list.json');

    $images = $mock->client()->images()->list(Filters::of(['dangling' => 'true']), all: true);

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/images/json?all=true&filters={"dangling":["true"]}')
        ->and($images)->toHaveCount(2)
        ->and($images[0])->toBeInstanceOf(ImageSummary::class);

    [$alpine, $dangling] = $images;

    expect($alpine->id)->toBe(IMAGES_TEST_ALPINE_ID)
        ->and($alpine->repoTags)->toBe(['quay.io/libpod/alpine:latest'])
        ->and($alpine->created?->getTimestamp())->toBe(1576799325)
        ->and($alpine->size)->toBe(5850080)
        ->and($alpine->containers)->toBe(2)
        ->and($alpine->labels)->toBe([])
        ->and($alpine->dangling)->toBeFalse()
        ->and($alpine->arch)->toBe('amd64')
        ->and($dangling->dangling)->toBeTrue()
        ->and($dangling->repoTags)->toBe([])
        ->and($dangling->names)->toBe([])
        ->and($dangling->labels)->toBe(['org.opencontainers.image.title' => 'app'])
        ->and($dangling->parentId)->toBe(IMAGES_TEST_ALPINE_ID);
});

it('sends no query when listing with defaults', function (): void {
    $mock = mockPodman()->json([]);

    expect($mock->client()->images()->list())->toBe([])
        ->and($mock->lastTarget())->toBe('/libpod/images/json');
});

it('inspects an image', function (): void {
    $mock = mockPodman()->fixture('images/inspect.json');

    $image = $mock->client()->images()->inspect('quay.io/libpod/alpine:latest');

    expect($mock->lastTarget())->toBe('/libpod/images/quay.io%2Flibpod%2Falpine%3Alatest/json')
        ->and($image->id)->toBe(IMAGES_TEST_ALPINE_ID)
        ->and($image->created?->format('Y-m-d H:i:s.u'))->toBe('2019-12-19 23:48:45.162839')
        ->and($image->config?->cmd)->toBe(['/bin/sh'])
        ->and($image->config?->exposedPorts)->toHaveKey('8080/tcp')
        ->and($image->config?->entrypoint)->toBe([])
        ->and($image->config?->stopSignal)->toBe('SIGTERM')
        ->and($image->graphDriverName)->toBe('overlay')
        ->and($image->graphDriverData)->toHaveKey('UpperDir')
        ->and($image->rootFsType)->toBe('layers')
        ->and($image->rootFsLayers)->toHaveCount(1)
        ->and($image->history)->toHaveCount(2)
        ->and($image->history[0]->emptyLayer)->toBeFalse()
        ->and($image->history[1]->emptyLayer)->toBeTrue()
        ->and($image->history[1]->createdBy)->toContain('CMD')
        ->and($image->annotations)->toBe([])
        ->and($image->healthcheck['Retries'] ?? null)->toBe(3);
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'image not known');
    $images = $mock->client()->images();

    expect($images->exists('alpine'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/images/alpine/exists')
        ->and($images->exists('nope'))->toBeFalse();
});

it('removes an image', function (): void {
    $mock = mockPodman()->fixture('images/remove.json');

    $report = $mock->client()->images()->remove('alpine', force: true, ignore: true);

    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/images/alpine?force=true&ignore=true')
        ->and($report->deleted)->toBe([IMAGES_TEST_ALPINE_ID])
        ->and($report->untagged)->toHaveCount(2)
        ->and($report->errors)->toBe([])
        ->and($report->hasErrors())->toBeFalse()
        ->and($report->exitCode)->toBe(0);
});

it('surfaces a conflict when removing an image in use', function (): void {
    mockPodman()->error(409, 'image is in use by a container')->client()->images()->remove('alpine');
})->throws(ConflictException::class, 'in use');

it('removes several images, always sending the all flag', function (): void {
    $mock = mockPodman()->fixture('images/remove-many.json');

    $report = $mock->client()->images()->removeMany(new ImageRemoveManyOptions(images: ['alpine', 'nope'], ignore: true));

    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/images/remove?images=alpine&images=nope&all=false&ignore=true')
        ->and($report->hasErrors())->toBeTrue()
        ->and($report->errors)->toBe(['nope: image not known'])
        ->and($report->exitCode)->toBe(1);
});

it('prunes images', function (): void {
    $mock = mockPodman()->fixture('images/prune.json');

    $reports = $mock->client()->images()->prune(new ImagePruneOptions(all: true, buildCache: true, filters: Filters::of(['until' => '24h'])));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/images/prune?all=true&buildcache=true&filters={"until":["24h"]}')
        ->and($reports)->toHaveCount(2)
        ->and($reports[0]->size)->toBe(6120448)
        ->and($reports[0]->error)->toBeNull()
        ->and($reports[1]->error)->not->toBeNull();
});

it('reads the image history', function (): void {
    $mock = mockPodman()->fixture('images/history.json');

    $history = $mock->client()->images()->history('alpine');

    expect($mock->lastTarget())->toBe('/libpod/images/alpine/history')
        ->and($history)->toHaveCount(2)
        ->and($history[0])->toBeInstanceOf(ImageHistoryEntry::class)
        ->and($history[0]->tags)->toBe(['quay.io/libpod/alpine:latest'])
        ->and($history[1]->id)->toBe('<missing>')
        ->and($history[1]->tags)->toBe([])
        ->and($history[1]->size)->toBe(5850080);
});

it('accepts the single-object history shape the spec documents', function (): void {
    $history = mockPodman()->json(['Id' => IMAGES_TEST_ALPINE_ID, 'Created' => 1576799325])->client()->images()->history('alpine');

    expect($history)->toHaveCount(1)
        ->and($history[0]->id)->toBe(IMAGES_TEST_ALPINE_ID);
});

it('renders the image tree', function (): void {
    $mock = mockPodman()->fixture('images/tree.json');

    $tree = $mock->client()->images()->tree('alpine', whatRequires: true);

    expect($mock->lastTarget())->toBe('/libpod/images/alpine/tree?whatrequires=true')
        ->and($tree)->toStartWith('Image ID: 9c6f07244728');
});

it('tags and untags images', function (): void {
    $mock = mockPodman()->noContent(201)->noContent(201)->noContent(201);
    $images = $mock->client()->images();

    $images->tag('alpine', 'localhost/alpine', 'mine');
    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/images/alpine/tag?repo=localhost/alpine&tag=mine');

    $images->untag('alpine', 'localhost/alpine', 'mine');
    expect($mock->lastTarget())->toBe('/libpod/images/alpine/untag?repo=localhost/alpine&tag=mine');

    $images->untag('alpine');
    expect($mock->lastTarget())->toBe('/libpod/images/alpine/untag');
});

it('pulls an image, folding the progress documents into one report', function (): void {
    $mock = mockPodman()->fixture('images/pull.json');

    $report = $mock->client()->images()->pull(
        'quay.io/libpod/alpine:latest',
        new ImagePullOptions(policy: 'missing', arch: 'arm64', tlsVerify: false),
        RegistryAuth::credentials('me', 'secret'),
    );

    $auth = $mock->lastRequest()->getHeaderLine(RegistryAuth::HEADER);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/images/pull?reference=quay.io/libpod/alpine:latest&Arch=arm64&policy=missing&tlsVerify=false')
        ->and(json_decode(base64_decode(strtr($auth, '-_', '+/')), true))->toBe(['username' => 'me', 'password' => 'secret'])
        ->and($report->failed())->toBeFalse()
        ->and($report->images)->toBe([IMAGES_TEST_ALPINE_ID])
        ->and($report->id)->toBe(IMAGES_TEST_ALPINE_ID)
        ->and($report->stream)->toStartWith("Trying to pull quay.io/libpod/alpine:latest...\nGetting image source signatures\n");
});

it('reports a pull failure that arrives after HTTP 200', function (): void {
    $mock = mockPodman()->fixture('images/pull-error.json');

    $report = $mock->client()->images()->pull('quay.io/libpod/nope:latest');

    expect($mock->lastRequest()->hasHeader(RegistryAuth::HEADER))->toBeFalse()
        ->and($report->failed())->toBeTrue()
        ->and($report->error)->toContain('unauthorized')
        ->and($report->images)->toBe([]);
});

it('pushes an image and returns the raw progress output', function (): void {
    $mock = mockPodman()->queue(new Response(200, ['Content-Type' => 'application/json'], '{"manifestdigest":"sha256:abc"}'));

    $output = $mock->client()->images()->push(
        'localhost/app:1',
        new ImagePushOptions(destination: 'quay.io/me/app:1', format: 'oci', retry: 3, quiet: true),
        RegistryAuth::token('tok'),
    );

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/images/localhost%2Fapp%3A1/push?destination=quay.io/me/app:1&quiet=true&format=oci&retry=3')
        ->and($mock->lastRequest()->hasHeader(RegistryAuth::HEADER))->toBeTrue()
        ->and($output)->toBe('{"manifestdigest":"sha256:abc"}');
});

it('searches registries', function (): void {
    $mock = mockPodman()->fixture('images/search.json');

    $results = $mock->client()->images()->search('alpine', new ImageSearchOptions(limit: 2, filters: Filters::of(['is-official' => 'true'])));

    expect($mock->lastTarget())->toBe('/libpod/images/search?term=alpine&limit=2&filters={"is-official":["true"]}')
        ->and($results)->toHaveCount(2)
        ->and($results[0])->toBeInstanceOf(ImageSearchResult::class)
        ->and($results[0]->name)->toBe('docker.io/library/alpine')
        ->and($results[0]->stars)->toBe(10000)
        ->and($results[0]->isOfficial())->toBeTrue()
        ->and($results[0]->isAutomated())->toBeFalse()
        ->and($results[1]->isOfficial())->toBeFalse()
        ->and($results[1]->tag)->toBeNull();
});

it('saves images as tar archives', function (): void {
    $mock = mockPodman()
        ->queue(new Response(200, ['Content-Type' => 'application/x-tar'], 'TAR1'))
        ->queue(new Response(200, ['Content-Type' => 'application/x-tar'], 'TAR2'));
    $images = $mock->client()->images();

    expect((string) $images->save('alpine', 'oci-archive', compress: true))->toBe('TAR1')
        ->and($mock->lastTarget())->toBe('/libpod/images/alpine/get?format=oci-archive&compress=true')
        ->and((string) $images->saveMany(['alpine', 'busybox'], 'docker-archive'))->toBe('TAR2')
        ->and($mock->lastTarget())->toBe('/libpod/images/export?format=docker-archive&references=alpine&references=busybox');
});

it('loads an image archive', function (): void {
    $mock = mockPodman()->fixture('images/load.json');

    $names = $mock->client()->images()->load('TARBYTES');

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/images/load')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/x-tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('TARBYTES')
        ->and($names)->toBe(['quay.io/libpod/alpine:latest']);
});

it('loads an image archive from a path on the server', function (): void {
    $mock = mockPodman()->fixture('images/load.json');

    $names = $mock->client()->images()->loadLocal('/tmp/alpine.tar');

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/local/images/load?path=/tmp/alpine.tar')
        ->and($names)->toBe(['quay.io/libpod/alpine:latest']);
});

it('gates loading from a server path behind Podman 5.7', function (): void {
    MockPodman::withVersion(ApiVersion::V5_6)->client()->images()->loadLocal('/tmp/alpine.tar');
})->throws(UnsupportedApiVersionException::class);

it('imports a filesystem tarball', function (): void {
    $mock = mockPodman()->fixture('images/import.json');

    $id = $mock->client()->images()->import('TARBYTES', new ImageImportOptions(
        reference: 'localhost/imported:1',
        message: 'hello',
        changes: ['CMD=/bin/sh', 'ENV=A=1'],
    ));

    expect($mock->lastTarget())->toBe('/libpod/images/import?changes=CMD=/bin/sh&changes=ENV=A=1&message=hello&reference=localhost/imported:1')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/x-tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('TARBYTES')
        ->and($id)->toStartWith('sha256:3c2b1a0f');
});

it('imports from a URL without a body', function (): void {
    $mock = mockPodman()->fixture('images/import.json');

    $mock->client()->images()->import(null, new ImageImportOptions(url: 'https://example.com/rootfs.tar'));

    expect($mock->lastTarget())->toBe('/libpod/images/import?url=https://example.com/rootfs.tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('');
});

it('copies an image to another host', function (): void {
    $mock = mockPodman()->fixture('images/scp.json');

    $id = $mock->client()->images()->scp('alpine', 'remote::', quiet: true);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/images/scp/alpine?destination=remote::&quiet=true')
        ->and($id)->toBe('quay.io/libpod/alpine:latest');
});

it('throws NotFoundException for unknown images', function (): void {
    mockPodman()->error(404, 'image not known')->client()->images()->inspect('nope');
})->throws(NotFoundException::class, 'image not known');
