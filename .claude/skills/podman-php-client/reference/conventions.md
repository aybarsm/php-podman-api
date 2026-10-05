# Conventions reference

## Naming

| Thing | Rule | Example |
|---|---|---|
| Resource class | Plural tag name in PascalCase | `Containers`, `Images`, `Quadlets` |
| Accessor on PodmanClient | lcfirst of the class name | `$podman->containers()` |
| Resource method | Verb, without the resource prefix. Same name as the Podman CLI subcommand when one exists | `list`, `inspect`, `create`, `remove`, `exists`, `prune`, `start` |
| `*Delete` operations | `remove()` (matches `podman rm`) | `ContainerDelete` → `remove()` |
| `*DeleteAll` operations (batch) | `removeMany()` | `ImageDeleteAll` → `removeMany()` |
| Deprecated operations (spec says so) | Keep the spec-derived name, add `@deprecated` naming the replacement | `ManifestPushV3` → `pushV3()` |
| Single-field responses (`{Id}`, `{Tree}`, `{Names}`) | Return the scalar or list, not a one-field DTO | `create(): string`, `tree(): string` |
| Response DTO | Domain name, singular. Strip `Libpod`/`Report`/`Response` noise. Put the spec definition in `@see` | `ListContainer` → `ContainerSummary`, `InspectContainerData` → `ContainerInspect` |
| Option object (query) | `{Resource}{Action}Options` | `ContainerListOptions` |
| Request body object | `{Resource}{Action}Request`, or `…Spec` when the spec calls it a spec | `ContainerCreateSpec`, `NetworkCreateRequest` |
| DTO namespace | `Dto\{Singular resource}`. Shared shapes go in `Dto\Shared` | `Dto\Container\ContainerSummary` |
| Property | camelCase of the JSON key, as produced by `Scaffolder::propertyName()` | `ImageID` → `imageId`, `IPAddress` → `ipAddress` |
| Path argument | `$nameOrId` for name-or-id params. Otherwise the spec name | `inspect(string $nameOrId)` |

## Spec type → PHP

| Spec | Property type | Reader |
|---|---|---|
| `string` | `?string` (identity fields: `string`) | `Data::stringOrNull` / `Data::string` |
| `string(date-time)` | `?DateTimeImmutable` | `Data::dateTimeOrNull` (truncates ns, also accepts unix ints) |
| `integer` (any format) | `?int` | `Data::intOrNull` (uint64 beyond PHP_INT_MAX clamps) |
| `number` | `?float` | `Data::floatOrNull` |
| `boolean` | `bool` (omitempty drops `false`) | `Data::bool` |
| `list<string>` | `array` + `@var list<string>` | `Data::stringList` (`null` → `[]`) |
| `list<integer>` | `array` + `@var list<int>` | `Data::intList` |
| `map<string>` | `array` + `@var array<string, string>` | `Data::stringMap` |
| `$ref` object you model | `?ChildDto` | `Data::objectOrNull($d, 'Key', ChildDto::fromArray(...))` |
| `list<$ref>` | `array` + `@var list<ChildDto>` | `Data::objectList` |
| `map<$ref>` | `array` + `@var array<string, ChildDto>` | `Data::objectMap` |
| Anything else / unmodelled | `array` + `@var array<string, mixed>` | `Data::map` |
| Top-level JSON array response | `list<Dto>` | `Data::listOf($result->jsonList(), Dto::fromArray(...))` |

Enumerated string values (spec `enum:` lists, or a closed set listed in a parameter or field *description*, such as
container state or the `status` filter) become a string-backed enum in `src/Enums/`. For response fields, keep the raw `?string` alongside the enum, or use `Enum::tryFrom()`. Podman adds values over time, so hydration must never throw on an unknown value.

Before you add an enum, check `src/Enums/` so you don't create a duplicate. Podman sometimes capitalises response values
differently from filter values (pods: `Running` vs `running`), so give such enums a lenient `fromResponse()`.

## Go-specific wire quirks

| Go type in the spec | Wire reality | Read with |
|---|---|---|
| `error` (`Err`, `Error`) | string, or `{}` when not stringified | `Data::errorOrNull()` |
| `[]error` (`Errs`) | list of strings or `{}` | `Data::errorList()` |
| `map[string]error` (`RemovedCtrs`) | values are null on success | `Data::errorMap()` |
| `[]byte` (spec says `list<integer(uint8)>`) | **base64 string** | `Data::stringOrNull()`, documented as base64 |
| `time.Time` zero value | `0001-01-01T00:00:00Z` | `Data::dateTimeOrNull()` returns null |
| `net.IPNet` | often a CIDR string | accept both shapes |

## Response shapes the spec gets wrong

| Situation | Rule |
|---|---|
| Spec says one object, server sends an array (or vice versa) | `Result::jsonListOrObject()`, and the method returns `list<Dto>` |
| Progress stream (pull, push, load): concatenated JSON documents | `Result::jsonDocuments()`. Build the report from the final document. If the report has an `error` field that can be set on HTTP 200, expose it (for example `failed()`) |
| 409 body is a `*Report` (`{Id, Errs}`) instead of an ErrorModel | Nothing to do. The transport turns `Errs` into the exception message |
| Libpod route documents no body, but its compat twin (served by the same handler) or a named generic definition does | Use that definition and cite it in `@see` (for example `ExecInspect` → `InspectExecSession`) |
| The spec documents nothing for a body the operation obviously needs, either request or response | **Defer it** in `dev-tools/deferred-operations.php` with the reason. Never invent parameters or fields (for example `PlayKubeDown`, `ContainerChanges`) |
| Response is `text/plain` with no schema | Return `string` via `Result::text()` |

## Large shapes

Some Go structs carry 100+ fields: `SpecGenerator` (container create), `PodSpecGenerator`, and `InspectContainerData.HostConfig`. Handle them like this:
- **Request bodies:** model the commonly used fields as typed constructor params. Add `public array $extra = []`, which is merged last in `toBody()`, so callers can send any other spec field. Every typed field must exist in the spec.
- **Response DTOs:** model the fields users actually read, and keep large nested blobs as `array<string, mixed>` (for example `hostConfig`). Note in the docblock that the shape comes from `bin/spec show <Def>`.

## Request bodies and query

- **Booleans:** `false` booleans that equal the spec default are omitted with `$flag ?: null`, which keeps URLs minimal and
  matches what the CLI sends. Send an explicit `false` only when the spec default is `true`.
- **Uploads:** string and stream bodies default to `text/plain` and `application/octet-stream`. Always pass
  `contentType:` explicitly (`application/x-tar`, `application/octet-stream`, `application/yaml` and so on) and follow
  the spec's `consumes` or `Content-Type` header enum literally, even when it is odd (`plain/text` for kube play).
- **Null handling:** `toQuery()` returns every param, null included, and the transport drops nulls. `toBody()` also returns nulls, which the transport strips recursively.
- **Empty maps:** default an optional map or list to `null`, not `[]`, because `[]` serialises as a JSON array and Go cannot decode that into a map.
- **Filters:** use `?Filters $filters` (from `Dto\Shared\Filters`) for every `filters` param.
- **Repeated params:** use `list<string>` for params repeated in the query string (`?names=a&names=b`).
- **JSON-in-query params:** some params are JSON strings, for example secrets `labels` or `driveropts`. Accept a PHP `array<string, string>` and `json_encode` it inside `toQuery()`.

## `@see` format

- **Definitions:** `@see resources/podman/swagger-v5.8.yaml#/definitions/X`
- **Degraded definitions:** `@see resources/podman/swagger-v5.7.yaml#/definitions/X (degraded in v5.8)`
- **Inline schemas:** `@see resources/podman/swagger-v5.8.yaml#/paths/~1libpod~1containers~1{name}~1exec/post` (JSON pointer, `/` → `~1`)

## Versioning

- **Operation level:** `Operation::since()` is generated, and the transport throws `UnsupportedApiVersionException` before sending.
- **Parameter level:** only documented. Add `@since Podman X.Y` on the option property, because Podman ignores unknown query keys.
  `bin/spec show <Op>` prints `[since X.Y]` for every parameter newer than the operation itself.
  - When a parameter was *renamed* (for example `Ignore` → `ignore` in 5.8), send the name that matches
    `$this->transport->config()->apiVersion` (see `Containers::stop()`).
- **Field level:** new response fields are always nullable, so older servers hydrate fine.

## Errors

- **Exceptions come from the transport:** resources never inspect status codes themselves.
- **Exceptions to that rule:**
  - `probe()` turns a 404 into `false`.
  - `Result::isNotModified()` turns a 304 into `false` for state-change methods.
- **Docblocks:** add `@throws` for the spec-listed statuses that callers can act on: `NotFoundException` and `ConflictException`.
