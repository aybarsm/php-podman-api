<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Container\ContainerLogsOptions;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Enums\LogStream;
use Aybarsm\Podman\Api\Exceptions\HydrationException;
use Aybarsm\Podman\Api\Exceptions\ServerException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;
use GuzzleHttp\Psr7\Response;

function logFrame(int $type, string $payload): string
{
    return pack('Cx3N', $type, strlen($payload)).$payload;
}

it('decodes multiplexed log frames into lines per stream', function (): void {
    $body = logFrame(1, "out1\n").logFrame(2, "err1\n").logFrame(1, "out2\r\nout3\n");
    $mock = mockPodman()->queue(new Response(200, [], $body));

    $lines = $mock->client()->containers()->logs('web', new ContainerLogsOptions(tail: 50));

    expect($mock->lastTarget())->toBe('/libpod/containers/web/logs?stdout=true&stderr=true&tail=50&follow=false')
        ->and(array_map(static fn ($l): string => $l->stream->value.':'.$l->text, $lines))
        ->toBe(['stdout:out1', 'stderr:err1', 'stdout:out2', 'stdout:out3'])
        ->and($lines[0]->timestamp)->toBeNull();
});

it('parses timestamps when requested', function (): void {
    $body = logFrame(1, "2026-10-05T18:43:45.961919920+03:00 hello world\n");
    $mock = mockPodman()->queue(new Response(200, [], $body));

    $lines = $mock->client()->containers()->logs('web', new ContainerLogsOptions(stderr: false, timestamps: true, since: '10m'));

    expect($mock->lastTarget())->toBe('/libpod/containers/web/logs?stdout=true&timestamps=true&since=10m&follow=false')
        ->and($lines[0]->text)->toBe('hello world')
        ->and($lines[0]->stream)->toBe(LogStream::Stdout)
        ->and($lines[0]->timestamp?->format('Y-m-d H:i:s.u P'))->toBe('2026-10-05 18:43:45.961919 +03:00');
});

it('returns no lines for empty output and rejects truncated frames', function (): void {
    $mock = mockPodman()->queue(new Response(200, [], ''))->queue(new Response(200, [], logFrame(1, 'abc').'x'));
    $containers = $mock->client()->containers();

    expect($containers->logs('web'))->toBe([])
        ->and(fn () => $containers->logs('web'))->toThrow(HydrationException::class, 'frame header');
});

it('reads a stats sample from the wrapped report Podman 5.8 sends', function (): void {
    $mock = mockPodman()->fixture('containers/stats.json');

    $stats = $mock->client()->containers()->statsAll(['web']);

    expect($mock->lastTarget())->toBe('/libpod/containers/stats?containers=web&stream=false')
        ->and($stats)->toHaveCount(1)
        ->and($stats[0]->name)->toBe('statsprobe')
        ->and($stats[0]->memUsage)->toBe(266240)
        ->and($stats[0]->cpu)->toBe(0.6759196065504781)
        ->and($stats[0]->network['ens33']['RxBytes'] ?? null)->toBe(978);
});

it('also accepts the bare ContainerStats object the spec documents', function (): void {
    $stats = mockPodman()->json(['ContainerID' => 'abc', 'PIDs' => 3])->client()->containers()->statsAll();

    expect($stats[0]->containerId)->toBe('abc')
        ->and($stats[0]->pids)->toBe(3);
});

it('throws when the stats report carries an error', function (): void {
    mockPodman()->json(['Error' => 'container web is not running', 'Stats' => null])->client()->containers()->statsAll(['web']);
})->throws(ServerException::class, 'container web is not running');

it('gates the 5.8-only all flag on older API versions', function (): void {
    MockPodman::withVersion(ApiVersion::V5_7)->client()->containers()->statsAll(all: true);
})->throws(UnsupportedApiVersionException::class, 'parameter "all"');
