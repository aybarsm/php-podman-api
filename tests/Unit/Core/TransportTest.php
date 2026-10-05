<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\ClientConfig;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Enums\ParameterGating;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\ConnectionException;
use Aybarsm\Podman\Api\Exceptions\ForbiddenException;
use Aybarsm\Podman\Api\Exceptions\HydrationException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\PodmanApiException;
use Aybarsm\Podman\Api\Exceptions\RequestException;
use Aybarsm\Podman\Api\Exceptions\ServerException;
use Aybarsm\Podman\Api\Exceptions\UnauthorizedException;
use Aybarsm\Podman\Api\Exceptions\UnexpectedStatusException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

it('builds versioned libpod URLs with encoded path parameters', function (): void {
    $mock = mockPodman()->json([]);

    $mock->transport()->send(Operation::ImageInspect, ['name' => 'quay.io/podman/hello:latest']);

    $uri = $mock->lastRequest()->getUri();
    expect((string) $uri)->toBe('http://d/v'.ApiVersion::latest()->value.'/libpod/images/quay.io%2Fpodman%2Fhello%3Alatest/json')
        ->and($mock->lastRequest()->getMethod())->toBe('GET');
});

it('uses the configured API version as the URL prefix', function (): void {
    $mock = MockPodman::withVersion(ApiVersion::V5_4)->json([]);

    $mock->transport()->send(Operation::ContainerList);

    expect($mock->lastRequest()->getUri()->getPath())->toBe('/v5.4.0/libpod/containers/json');
});

it('encodes booleans, repeated lists and JSON filters in the query string', function (): void {
    $mock = mockPodman()->json([]);

    $mock->transport()->send(Operation::ContainerList, query: [
        'all' => true,
        'size' => false,
        'limit' => 5,
        'last' => null,
        'filters' => Filters::of(['label' => ['app=web'], 'status' => 'running']),
    ]);

    expect($mock->lastTarget())->toBe('/libpod/containers/json?all=true&size=false&limit=5&filters={"label":["app=web"],"status":["running"]}');
});

it('repeats list parameters', function (): void {
    $mock = mockPodman()->json([]);

    $mock->transport()->send(Operation::ImageExport, query: ['references' => ['alpine', 'busybox']]);

    expect($mock->lastTarget())->toBe('/libpod/images/export?references=alpine&references=busybox');
});

it('omits empty filters', function (): void {
    $mock = mockPodman()->json([]);

    $mock->transport()->send(Operation::ContainerList, query: ['filters' => Filters::empty()]);

    expect($mock->lastTarget())->toBe('/libpod/containers/json');
});

it('sends array bodies as JSON without null members', function (): void {
    $mock = mockPodman()->json(['Id' => 'abc', 'Warnings' => []], 201);

    $mock->transport()->send(Operation::ContainerCreate, body: ['image' => 'alpine', 'name' => null, 'env' => ['A' => '1']]);

    expect($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($mock->lastJsonBody())->toBe(['image' => 'alpine', 'env' => ['A' => '1']]);
});

it('sends stream bodies verbatim with an explicit content type', function (): void {
    $mock = mockPodman()->noContent();

    $mock->transport()->send(Operation::VolumeImport, ['name' => 'data'], body: Utils::streamFor('tar-bytes'), contentType: 'application/x-tar');

    expect($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/x-tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('tar-bytes');
});

it('sends the user agent, configured headers and per-request headers', function (): void {
    $mock = mockPodman()->json([]);

    $mock->transport()->send(Operation::ImagePull, headers: ['X-Registry-Auth' => 'abc']);

    expect($mock->lastRequest()->getHeaderLine('User-Agent'))->toBe('aybarsm/podman-api')
        ->and($mock->lastRequest()->getHeaderLine('X-Registry-Auth'))->toBe('abc');
});

it('fails fast when a path parameter is missing', function (): void {
    mockPodman()->transport()->send(Operation::ContainerInspect);
})->throws(InvalidArgumentException::class, "Missing path parameter 'name'");

it('treats 304 Not Modified as success', function (): void {
    $result = mockPodman()->noContent(304)->transport()->send(Operation::ContainerStart, ['name' => 'web']);

    expect($result->isNotModified())->toBeTrue();
});

it('rejects operations newer than the configured API version before sending', function (): void {
    $mock = MockPodman::withVersion(ApiVersion::V5_4);

    expect(fn () => $mock->transport()->send(Operation::QuadletList))
        ->toThrow(function (UnsupportedApiVersionException $e): void {
            expect($e->operationId)->toBe('QuadletListLibpod')
                ->and($e->required)->toBe(ApiVersion::V5_7)
                ->and($e->configured)->toBe(ApiVersion::V5_4);
        })
        ->and($mock->requestCount())->toBe(0);
});

it('gates query parameters newer than the configured API version', function (): void {
    $mock = MockPodman::withVersion(ApiVersion::V5_7);

    expect(fn () => $mock->transport()->send(Operation::ContainerList, query: ['external' => true]))
        ->toThrow(function (UnsupportedApiVersionException $e): void {
            expect($e->parameter)->toBe('external')
                ->and($e->required)->toBe(ApiVersion::V5_8)
                ->and($e->getMessage())->toContain('parameterGating: ParameterGating::Off');
        })
        ->and($mock->requestCount())->toBe(0);
});

it('does not gate unset parameters or when gating is off', function (): void {
    $strict = MockPodman::withVersion(ApiVersion::V5_7)->json([]);
    $strict->transport()->send(Operation::ContainerList, query: ['external' => null, 'all' => true]);

    $off = (new MockPodman(ClientConfig::unixSocket('/tmp/x.sock', ApiVersion::V5_7)->withParameterGating(ParameterGating::Off)))->json([]);
    $off->transport()->send(Operation::ContainerList, query: ['external' => true]);

    expect($strict->lastTarget())->toBe('/libpod/containers/json?all=true')
        ->and($off->lastTarget())->toBe('/libpod/containers/json?external=true');
});

it('maps error statuses to exceptions carrying the ErrorModel', function (int $status, string $class): void {
    $mock = mockPodman()->error($status, 'no such container', 'no such object');

    try {
        $mock->transport()->send(Operation::ContainerInspect, ['name' => 'nope']);
        test()->fail('Expected an exception');
    } catch (RequestException $e) {
        expect($e)->toBeInstanceOf($class)
            ->toBeInstanceOf(PodmanApiException::class)
            ->and($e->statusCode)->toBe($status)
            ->and($e->apiMessage)->toBe('no such container')
            ->and($e->apiCause)->toBe('no such object')
            ->and($e->operationId)->toBe('ContainerInspectLibpod')
            ->and($e->getMessage())->toContain('HTTP '.$status);
    }
})->with([
    [400, BadRequestException::class],
    [401, UnauthorizedException::class],
    [403, ForbiddenException::class],
    [404, NotFoundException::class],
    [409, ConflictException::class],
    [500, ServerException::class],
    [503, ServerException::class],
    [418, UnexpectedStatusException::class],
]);

it('reads pod report errors from 409 bodies that are not an ErrorModel', function (): void {
    $mock = mockPodman()->json(['Id' => 'abc', 'Errs' => ['container x: already stopped', 'container y: busy']], 409);

    expect(fn () => $mock->transport()->send(Operation::PodStop, ['name' => 'p']))
        ->toThrow(ConflictException::class, 'container x: already stopped; container y: busy');
});

it('reads JSON that is either a list or a single object', function (): void {
    $mock = mockPodman()->json([['Id' => 'a']])->json(['Id' => 'b'])->json(null);
    $transport = $mock->transport();

    expect($transport->send(Operation::PodPrune)->jsonListOrObject())->toBe([['Id' => 'a']])
        ->and($transport->send(Operation::PodPrune)->jsonListOrObject())->toBe([['Id' => 'b']])
        ->and($transport->send(Operation::PodPrune)->jsonListOrObject())->toBe([]);
});

it('falls back to the raw body when an error is not JSON', function (): void {
    $mock = mockPodman()->queue(new Response(500, [], 'plain failure'));

    expect(fn () => $mock->transport()->send(Operation::SystemInfo))
        ->toThrow(ServerException::class, 'plain failure');
});

it('wraps PSR-18 client failures in ConnectionException', function (): void {
    $mock = mockPodman()->queue(new ConnectException('socket missing', new Request('GET', 'http://d')));

    expect(fn () => $mock->transport()->send(Operation::SystemPing))
        ->toThrow(ConnectionException::class, 'socket missing');
});

it('decodes concatenated and newline-delimited JSON document streams', function (): void {
    $body = '{"stream":"Copying blob \"a{b}\"\n"}{"stream":"done"}'."\n".'{"images":["abc"],"id":"abc"}';
    $mock = mockPodman()->queue(new Response(200, [], $body))->queue(new Response(200, [], '{"a":1}{"b":'));
    $transport = $mock->transport();

    expect($transport->send(Operation::ImagePull)->jsonDocuments())->toBe([
        ['stream' => "Copying blob \"a{b}\"\n"],
        ['stream' => 'done'],
        ['images' => ['abc'], 'id' => 'abc'],
    ])
        ->and(fn () => $transport->send(Operation::ImagePull)->jsonDocuments())->toThrow(HydrationException::class, 'truncated');
});

it('decodes JSON objects and lists, treating null as an empty list', function (): void {
    $mock = mockPodman()->json(['a' => 1])->json(null)->queue(new Response(200, [], 'not json'));
    $transport = $mock->transport();

    expect($transport->send(Operation::SystemInfo)->jsonObject())->toBe(['a' => 1])
        ->and($transport->send(Operation::ContainerList)->jsonList())->toBe([])
        ->and(fn () => $transport->send(Operation::SystemInfo)->jsonObject())->toThrow(HydrationException::class);
});
