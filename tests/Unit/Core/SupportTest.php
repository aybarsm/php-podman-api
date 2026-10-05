<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\ClientConfig;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Shared\RegistryAuth;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Exceptions\HydrationException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Aybarsm\Podman\Api\Internal\Support\Query;
use Aybarsm\Podman\Api\Internal\Transport\GuzzleFactory;
use Aybarsm\Podman\Api\Internal\Transport\HttpMethod;
use Aybarsm\Podman\Api\PodmanClient;
use GuzzleHttp\RequestOptions;

describe('ApiVersion', function (): void {
    it('orders versions and exposes bounds', function (): void {
        expect(ApiVersion::minimum())->toBe(ApiVersion::V5_4)
            ->and(ApiVersion::latest())->toBe(ApiVersion::V5_8)
            ->and(ApiVersion::V5_6->isAtLeast(ApiVersion::V5_6))->toBeTrue()
            ->and(ApiVersion::V5_5->isAtLeast(ApiVersion::V5_6))->toBeFalse();
    });

    it('resolves server-reported versions to the closest known API version', function (string $server, ?ApiVersion $expected): void {
        expect(ApiVersion::fromServerVersion($server))->toBe($expected);
    })->with([
        ['5.6.2', ApiVersion::V5_6],
        ['5.8.0-dev', ApiVersion::V5_8],
        ['6.1.0', ApiVersion::V5_8],
        ['5.3.1', null],
        ['garbage', null],
    ]);
});

describe('Operation registry', function (): void {
    it('exposes method, path and minimum version per operation', function (): void {
        expect(Operation::ContainerList->method())->toBe(HttpMethod::Get)
            ->and(Operation::ContainerList->path())->toBe('/libpod/containers/json')
            ->and(Operation::ContainerList->since())->toBe(ApiVersion::V5_4)
            ->and(Operation::ArtifactList->since())->toBe(ApiVersion::V5_6)
            ->and(Operation::QuadletInstall->since())->toBe(ApiVersion::V5_8)
            ->and(Operation::SystemPing->value)->toBe('SystemPing');
    });

    it('covers only Libpod routes', function (): void {
        foreach (Operation::cases() as $operation) {
            expect($operation->path())->toStartWith('/libpod/');
        }
    });
});

describe('Query', function (): void {
    it('skips nulls and encodes reserved characters', function (): void {
        expect(Query::build(['a' => null, 'b' => 'x y&z', 'c' => 1.5]))->toBe('b=x%20y%26z&c=1.5');
    });
});

describe('Filters', function (): void {
    it('is immutable and appends values', function (): void {
        $base = Filters::of(['label' => 'a=b']);
        $more = $base->with('label', 'c=d')->with('status', 'running');

        expect($base->filters)->toBe(['label' => ['a=b']])
            ->and($more->filters)->toBe(['label' => ['a=b', 'c=d'], 'status' => ['running']])
            ->and(json_encode(Filters::empty()))->toBe('{}');
    });
});

describe('RegistryAuth', function (): void {
    it('encodes credentials as base64url JSON for X-Registry-Auth', function (): void {
        $header = RegistryAuth::credentials('me', 'p+ss/word?', 'quay.io')->toHeader();

        expect($header)->not->toContain('+')->not->toContain('/')
            ->and(json_decode(base64_decode(strtr($header, '-_', '+/')), true))->toBe([
                'username' => 'me',
                'password' => 'p+ss/word?',
                'serveraddress' => 'quay.io',
            ]);
    });

    it('supports identity tokens', function (): void {
        $decoded = json_decode(base64_decode(strtr(RegistryAuth::token('tok')->toHeader(), '-_', '+/')), true);

        expect($decoded)->toBe(['identitytoken' => 'tok']);
    });
});

describe('ClientConfig and Guzzle defaults', function (): void {
    it('passes the unix socket path to curl', function (): void {
        $options = GuzzleFactory::options(ClientConfig::unixSocket('/run/user/1000/podman/podman.sock'));

        expect($options['curl'])->toBe([CURLOPT_UNIX_SOCKET_PATH => '/run/user/1000/podman/podman.sock'])
            ->and($options[RequestOptions::HTTP_ERRORS])->toBeFalse();
    });

    it('does not set a socket for TCP connections', function (): void {
        $config = ClientConfig::tcp('http://127.0.0.1:8080/', ApiVersion::V5_5);

        expect(GuzzleFactory::options($config))->not->toHaveKey('curl')
            ->and($config->baseUri)->toBe('http://127.0.0.1:8080')
            ->and($config->apiVersion)->toBe(ApiVersion::V5_5)
            ->and($config->usesUnixSocket())->toBeFalse();
    });

    it('defaults to the newest API version and rejects bad base URIs', function (): void {
        expect(ClientConfig::unixSocket()->apiVersion)->toBe(ApiVersion::latest())
            ->and(ClientConfig::unixSocket()->withApiVersion(ApiVersion::V5_4)->apiVersion)->toBe(ApiVersion::V5_4)
            ->and(fn () => new ClientConfig('unix:///run/podman.sock'))->toThrow(InvalidArgumentException::class);
    });

    it('builds a client from the factories', function (): void {
        expect(PodmanClient::unixSocket('/tmp/x.sock', ApiVersion::V5_6)->config()->apiVersion)->toBe(ApiVersion::V5_6)
            ->and(PodmanClient::tcp('http://localhost:8888')->config()->baseUri)->toBe('http://localhost:8888');
    });
});

describe('Data', function (): void {
    it('reads typed scalars and treats missing keys as null', function (): void {
        $d = ['s' => 'x', 'i' => 3, 'f' => 2, 'b' => true, 'big' => 1.8446744073709552E19, 'whole' => 4.0];

        expect(Data::string($d, 's'))->toBe('x')
            ->and(Data::stringOrNull($d, 'missing'))->toBeNull()
            ->and(Data::int($d, 'i'))->toBe(3)
            ->and(Data::int($d, 'whole'))->toBe(4)
            ->and(Data::int($d, 'big'))->toBe(PHP_INT_MAX)
            ->and(Data::float($d, 'f'))->toBe(2.0)
            ->and(Data::bool($d, 'b'))->toBeTrue()
            ->and(Data::bool($d, 'missing'))->toBeFalse();
    });

    it('throws HydrationException on type mismatches and missing required keys', function (): void {
        expect(fn () => Data::string(['s' => 1], 's'))->toThrow(HydrationException::class, 'Expected "s" to be string, got int')
            ->and(fn () => Data::string([], 's'))->toThrow(HydrationException::class, 'Required key "s"')
            ->and(fn () => Data::int(['i' => 1.5], 'i'))->toThrow(HydrationException::class)
            ->and(fn () => Data::stringList(['l' => [1]], 'l'))->toThrow(HydrationException::class);
    });

    it('parses RFC 3339 nanosecond timestamps and unix timestamps', function (): void {
        $d = ['t' => '2024-05-01T10:20:30.123456789+02:00', 'u' => 1714551630, 'z' => ''];

        expect(Data::dateTime($d, 't')->format('Y-m-d\TH:i:s.uP'))->toBe('2024-05-01T10:20:30.123456+02:00')
            ->and(Data::dateTime($d, 'u')->getTimestamp())->toBe(1714551630)
            ->and(Data::dateTimeOrNull($d, 'z'))->toBeNull();
    });

    it('reads lists and maps, treating Go nil as empty', function (): void {
        $d = ['l' => ['a', 'b'], 'n' => null, 'm' => ['k' => 'v'], 'il' => [1, 2]];

        expect(Data::stringList($d, 'l'))->toBe(['a', 'b'])
            ->and(Data::stringList($d, 'n'))->toBe([])
            ->and(Data::stringMap($d, 'm'))->toBe(['k' => 'v'])
            ->and(Data::stringMap($d, 'missing'))->toBe([])
            ->and(Data::intList($d, 'il'))->toBe([1, 2]);
    });

    it('reads Go error values, lists and maps', function (): void {
        $d = ['e' => 'boom', 'list' => ['a', null, [], 'b'], 'map' => ['c1' => null, 'c2' => 'busy', 'c3' => []]];

        expect(Data::errorOrNull($d, 'e'))->toBe('boom')
            ->and(Data::errorOrNull(['m' => ['message' => 'x']], 'm'))->toBe('x')
            ->and(Data::errorList($d, 'list'))->toBe(['a', 'unspecified error (not serialised by Podman)', 'b'])
            ->and(Data::errorMap($d, 'map'))->toBe(['c1' => null, 'c2' => 'busy', 'c3' => 'unspecified error (not serialised by Podman)']);
    });

    it('hydrates nested objects through factories', function (): void {
        $factory = static fn (array $a): string => Data::string($a, 'Name');
        $d = ['one' => ['Name' => 'a'], 'many' => [['Name' => 'b'], ['Name' => 'c']], 'byKey' => ['x' => ['Name' => 'd']]];

        expect(Data::object($d, 'one', $factory))->toBe('a')
            ->and(Data::objectOrNull($d, 'missing', $factory))->toBeNull()
            ->and(Data::objectList($d, 'many', $factory))->toBe(['b', 'c'])
            ->and(Data::objectMap($d, 'byKey', $factory))->toBe(['x' => 'd'])
            ->and(Data::listOf([['Name' => 'e']], $factory))->toBe(['e']);
    });
});
