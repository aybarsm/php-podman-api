# aybarsm/podman-api

Typed PHP client for the **Podman Libpod REST API**. Namespace `Aybarsm\Podman\Api\`, PHP ^8.3.

**The only source of truth is `resources/podman/swagger-v{X.Y}.yaml`.** Never invent endpoints, parameters, or fields. If the spec is unclear, check it with `bin/spec show …` before writing code.

For adding resources or upgrading to a new Podman spec, **use the project skill** at `.claude/skills/podman-php-client/`. It has the step-by-step workflows and copy-ready templates.

## Commands

```bash
composer test                  # unit + arch suites, then PHPStan (the gate for every change)
composer test:unit             # Pest: tests/Unit + tests/Arch
composer test:integration      # Pest: tests/Integration, needs PODMAN_SOCKET=/path/to/podman.sock
composer analyse               # PHPStan level max (src + dev-tools), enforces #[Override]
vendor/bin/pest --filter=…     # single test

bin/spec ops [--tag=containers]          # Libpod operations + minimum version
bin/spec show <OperationId|Definition>   # params, responses, properties (auto-fallback for degraded defs)
bin/spec diff 5.7 5.8                    # operations/params/definitions changed between specs
bin/spec degraded                        # shapeless definitions in the newest spec + where to read them instead
bin/spec scaffold dto <Def> <Ns> [Class] # starting-point readonly DTO
bin/spec scaffold resource <tag> <Class> # starting-point resource stubs
bin/spec operation-enum [--check]        # regenerate / verify src/Internal/Operation.php
bin/spec coverage [-v]                   # implemented / deferred / missing Libpod operations
```

## Architecture

```
src/
  PodmanClient.php          entry point: factories (unixSocket, tcp, create) + resource accessors
  ClientConfig.php          baseUri, socketPath, apiVersion, timeouts, headers
  Contracts/                Hydratable (DTO::fromArray), QueryParameters (toQuery), RequestBody (toBody)
  Enums/ApiVersion.php      one case per spec file: V5_4 … V5_8
  Enums/…                   backed enums for values the spec enumerates
  Resources/                one final readonly class per Libpod tag, extends AbstractResource
  Dto/{Resource}/           response DTOs + request option/body objects (final readonly)
  Dto/Shared/               Filters, RegistryAuth, shared shapes
  Exceptions/               PodmanApiException hierarchy
  Internal/                 @internal: never part of the public API
    Operation.php           GENERATED registry: operationId → method, path, since()
    Transport/Transport.php the ONLY class that touches PSR-7 requests/responses
    Transport/Result.php    decoded response handed to resources (json/jsonObject/jsonList/text/stream)
    Support/Data.php        typed readers for DTO hydration
    Support/Query.php       query encoding (bools, repeated lists, JSON filters)
dev-tools/                  spec tooling (dev only, namespace Aybarsm\Podman\Api\Dev), not shipped
bin/spec                    CLI entry for dev-tools
tests/  Unit/ Arch/ Integration/ Fixtures/ Support/MockPodman.php
```

**How a request flows.** A resource method calls `$this->transport->send(Operation::X, path: […], query: […], body: …)`. The transport then:
1. checks `Operation::since()` against `ClientConfig::$apiVersion`, throwing `UnsupportedApiVersionException` if the operation is too new
2. builds `{baseUri}/v{apiVersion}{path}`
3. sends the request through PSR-18
4. maps any status other than 2xx/304 to an exception
5. returns a `Result`

The resource then hydrates DTOs from the `Result`.

## Non-negotiable conventions

These are enforced by `tests/Arch` and PHPStan.

- **`declare(strict_types=1);` in every PHP file:** src, dev-tools, tests and bin.
- **Typed class constants:** `private const string BASE = '…';`.
- **`#[Override]`** on every method that implements an interface method or overrides a parent method, such as `fromArray`, `toQuery`, `toBody` and `jsonSerialize`.
- **Class modifiers:**
  - DTOs, resources, `PodmanClient` and `ClientConfig` are `final readonly`.
  - Exceptions are `final`. The only exceptions to that are the abstract `PodmanApiException` and `RequestException`.
- **Enums:** public enums are string-backed and live in `src/Enums`.
- **Return types: never return `ResponseInterface`, `Result` or `Transport`.** Public resource methods return one of:
  - a DTO
  - `list<Dto>`
  - `bool`: used for `exists`, and for start/stop-style operations where `false` means HTTP 304, already in that state
  - `void`: for 204 responses
  - `string`: for text payloads
  - PSR-7 `StreamInterface`: only for tar/octet-stream payloads
- **One `Operation` per method.** Each resource method maps to exactly one `Operation` case, so `bin/spec coverage` counts it.
- **Hydration happens in `fromArray()` through `Data::*`.**
  - Spec-optional fields are nullable, and identity fields (`Id`, `Name`) use the non-null readers.
  - Lists and maps default to `[]`, and missing booleans read as `false`.
  - Unknown keys are ignored.
- **Request bodies drop `null` members.** Default an optional map or list to `null`, not `[]`: `[]` serialises as a JSON array, which Go rejects for maps.
- **`Internal\*` is not public API.** Users only touch `PodmanClient`, `ClientConfig`, resources, DTOs, enums and exceptions.
- **Never edit `src/Internal/Operation.php` by hand.** Regenerate it with `bin/spec operation-enum`. A unit test fails when it is stale.

## Exceptions

```
PodmanApiException (abstract)
├─ ConnectionException             PSR-18 transport failure
├─ UnsupportedApiVersionException  operation newer than configured apiVersion (thrown before sending)
├─ HydrationException              payload shape ≠ spec
└─ RequestException (abstract)     ->statusCode, ->apiMessage, ->apiCause, ->operationId
   ├─ BadRequestException 400, UnauthorizedException 401, ForbiddenException 403
   ├─ NotFoundException 404, ConflictException 409, ServerException 5xx
   └─ UnexpectedStatusException
```

## Spec quirks you must know

- **v5.8 is degraded.** About 40 definitions reachable from Libpod operations are bare `type: object`, including `ListContainer`, `ImageSummary` and most `*Report` types. Routes and params in v5.8 are reliable, but shapes are not. `bin/spec show/scaffold` automatically falls back to the newest older spec that has the shape, following renames (for example `ImageSummary` → v5.7 `LibpodImageSummary`). Run `bin/spec degraded` to see the full list.
- **Path params are `rawurlencode`d.** Podman uses `UseEncodedPath()`, so `quay.io/a/b:tag` is sent as a single segment.
- **Query parameter quirks:**
  - `filters` is JSON; use `Dto\Shared\Filters`.
  - Lists repeat the key.
  - Booleans are `true`/`false`.
  - Unknown query params are ignored by Podman, so param-level version differences are documented with `@since` in option DTOs and are not gated.
- **304** comes back from start/stop/init when the container is already in that state. It is not an error.
- **`X-Registry-Auth` is base64url JSON.** Build it with `Dto\Shared\RegistryAuth::toHeader()`.
- **`_ping` is unversioned.** The spec says so in the operation description, and the generator turns that into `Operation::isVersioned()`, so the transport omits the `/v{version}` prefix for it.
- **Progress bodies** (pull, push, load and similar) are several JSON documents concatenated together. Read them with `Result::jsonDocuments()`.
- **Empty request bodies are sent as `{}`.** Go structs reject `[]`.
- **JSON-encoded query values** (`rename`, `labels`, `driveropts`) go through `Query::json()`.
- **Go zero time** (`0001-01-01T00:00:00Z`) hydrates to `null`.
- **Go error types** may arrive as `{}`:
  - `error` (`Err`): read with `Data::errorOrNull()`
  - `[]error` (`Errs`): read with `Data::errorList()`
  - `map[string]error`: read with `Data::errorMap()`
- **Go `[]byte`** is typed `list<integer(uint8)>` in the spec but arrives as a base64 string.
- **Wrong response cardinality:** several operations document a single object but return an array (image history and search, pod prune). Use `Result::jsonListOrObject()`.
- **Pod 409 bodies** are `Pod*Report` objects. The transport folds their `Errs` into the `ConflictException` message.
- **The spec is the contract.** When it documents no body that an operation needs (`PlayKubeDown`, the `*Changes` operations), the operation is deferred, never guessed. The conventions in the skill list the exact rules.

## Scope

- **In scope:** every non-streaming Libpod operation.
- **Out of scope:** the Docker-compat API (`/containers/…` without `/libpod`).
- **Deferred:** everything listed in `dev-tools/deferred-operations.php`.
  - Streaming and hijacked operations: attach, logs, stats (`stream`), events, exec start, build.
  - Operations whose spec omits a needed body: container/image changes, checkpoint/restore, image resolve, kube down.

## Testing

- **Unit tests** use `Tests\Support\MockPodman`, a Guzzle `MockHandler` injected as the PSR-18 client with request history. Assert the URL (`lastTarget()`), the body (`lastJsonBody()`) and the hydrated DTOs. JSON fixtures live in `tests/Fixtures/responses/{resource}/` and must match spec shapes.
- **Arch tests** (`tests/Arch`) enforce the conventions above. Do not weaken them; fix the code.
- **Integration tests** (`tests/Integration`) skip themselves unless `PODMAN_SOCKET` is set. On macOS, get the socket from `podman machine inspect --format '{{.ConnectionInfo.PodmanSocket.Path}}'`.
