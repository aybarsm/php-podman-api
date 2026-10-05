# aybarsm/podman-api

A typed PHP client for the [Podman](https://podman.io) **Libpod REST API**, generated from and checked against Podman's own swagger specs (Podman API 5.4–5.8).

- **Resource-oriented:** `$podman->containers()->list()`, `$podman->images()->pull()`, `$podman->pods()->start()`.
- **Typed results:** every response is hydrated into a readonly DTO. Responses are never raw PSR-7 objects.
- **Version-aware:** operations newer than your server's API version throw before any request is sent.
- **PSR-18 / PSR-17:** Guzzle is the default, and any compliant client can be injected.

## Requirements

- PHP 8.3+
- `ext-curl` for unix-socket connections (the default Podman transport)
- A running Podman service: `podman system service -t 0` (rootless socket: `/run/user/$UID/podman/podman.sock`)

## Installation

```bash
composer require aybarsm/podman-api
```

## Quick start

```php
use Aybarsm\Podman\Api\PodmanClient;
use Aybarsm\Podman\Api\Dto\Container\ContainerCreateSpec;
use Aybarsm\Podman\Api\Dto\Container\ContainerListOptions;
use Aybarsm\Podman\Api\Dto\Container\PortMapping;
use Aybarsm\Podman\Api\Dto\Shared\Filters;

$podman = PodmanClient::unixSocket('/run/user/1000/podman/podman.sock');

$podman->images()->pull('quay.io/libpod/alpine:latest');

$created = $podman->containers()->create(new ContainerCreateSpec(
    image: 'quay.io/libpod/alpine:latest',
    name: 'web',
    command: ['sleep', 'infinity'],
    labels: ['app' => 'demo'],
    portMappings: [new PortMapping(containerPort: 80, hostPort: 8080)],
));

$podman->containers()->start('web');                 // false if it was already running (HTTP 304)

foreach ($podman->containers()->list(new ContainerListOptions(filters: Filters::of(['label' => 'app=demo']))) as $c) {
    echo $c->name(), ' ', $c->status, PHP_EOL;
}

$inspect = $podman->containers()->inspect('web');
$inspect->state->running;                            // bool
$inspect->env();                                     // ['PATH' => '…', …]

$podman->containers()->stop('web', timeout: 5);
$podman->containers()->remove('web');
```

## Connecting

```php
use Aybarsm\Podman\Api\ClientConfig;
use Aybarsm\Podman\Api\Enums\ApiVersion;

PodmanClient::unixSocket();                                        // /run/podman/podman.sock, newest API version
PodmanClient::unixSocket('/run/user/1000/podman/podman.sock', ApiVersion::V5_4);
PodmanClient::tcp('http://127.0.0.1:8888');                        // podman system service tcp://127.0.0.1:8888

// Full control, including your own PSR-18 client and PSR-17 factories:
PodmanClient::create(
    new ClientConfig(socketPath: '/run/podman/podman.sock', apiVersion: ApiVersion::V5_6, timeout: 30.0),
    httpClient: $psr18Client,
    requestFactory: $psr17Factory,
    streamFactory: $psr17Factory,
);
```

**Query parameters are gated too.** Setting a parameter that the spec only introduces in a newer version (for example
`ContainerListOptions::$external`, 5.8) throws before sending. Older spec files occasionally omitted parameters that
servers already accepted. If you hit one of those, opt out with
`new ClientConfig(..., parameterGating: ParameterGating::Off)`.

**Pin the version your server speaks.** `ApiVersion::fromServerVersion($podman->system()->version()->version)` maps a server version such as `5.6.2` to the right case. If you call an operation the configured version doesn't have (for example `artifacts()` before 5.6), the client throws `UnsupportedApiVersionException` without contacting the server.

## Resources

| Accessor | Covers |
|---|---|
| `system()` | ping, info, version, disk usage, prune, storage check |
| `containers()` | list, inspect, create, start/stop/restart/kill, pause/unpause, init, wait, top, rename, update, healthcheck, mount, archive get/put, export, commit, prune, remove |
| `images()` | list, inspect, pull, push, tag/untag, history, tree, search, save/load, import, scp, prune, remove |
| `manifests()` | create, inspect, modify, push, remove |
| `pods()` | list, inspect, create, start/stop/restart/kill, pause/unpause, top, stats, prune, remove |
| `networks()` | list, inspect, create, connect/disconnect, update, prune, remove |
| `volumes()` | list, inspect, create, export/import, prune, remove |
| `secrets()` | list, inspect, create, remove |
| `exec()` | create, inspect, resize exec sessions |
| `artifacts()` | OCI artifacts (Podman 5.6+): add, pull, push, extract, remove |
| `quadlets()` | Quadlet units (Podman 5.7+/5.8+): list, print, install, remove |
| `kube()` | generate Kubernetes YAML/systemd units, play, apply |

Registry credentials are passed explicitly:

```php
use Aybarsm\Podman\Api\Dto\Shared\RegistryAuth;

$podman->images()->pull('quay.io/me/private:1', auth: RegistryAuth::credentials('me', $token, 'quay.io'));
```

## Errors

Everything thrown by this package extends `Aybarsm\Podman\Api\Exceptions\PodmanApiException`:

| Exception | When |
|---|---|
| `NotFoundException` (404) | no such container, image, pod, … |
| `ConflictException` (409) | name in use, container running, … |
| `BadRequestException` (400), `UnauthorizedException` (401), `ForbiddenException` (403), `ServerException` (5xx), `UnexpectedStatusException` | other error statuses. All of these expose `->statusCode`, `->apiMessage` and `->apiCause` |
| `ConnectionException` | the socket or host is unreachable |
| `UnsupportedApiVersionException` | the operation is newer than the configured API version |
| `HydrationException` | the response did not match the spec's shape |

`exists()` methods return `false` on 404 instead of throwing.

## Not supported (yet)

- **Streaming and hijacked endpoints:** attach, logs, stats streaming, events, exec start, image build.
- **Operations whose spec omits a body they need:** container/image changes, checkpoint/restore, image resolve, kube down.
- **The Docker-compatible API.**

The full list with reasons is in [`dev-tools/deferred-operations.php`](dev-tools/deferred-operations.php).

## Development

```bash
composer test                                   # Pest (unit + architecture) and PHPStan level max
PODMAN_SOCKET=/run/user/1000/podman/podman.sock PODMAN_PULL=1 composer test:integration
bin/spec coverage                               # implemented / deferred operations per tag
bin/spec diff 5.7 5.8                           # what changed between Podman spec versions
```

Contributors: [`CLAUDE.md`](CLAUDE.md) describes the architecture and conventions, and [`.claude/skills/podman-php-client/`](.claude/skills/podman-php-client/SKILL.md) holds the add-resource and spec-upgrade workflows.

## License

MIT
