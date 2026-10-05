# Workflow: add a resource or operations

Use this to implement a Libpod tag such as `secrets`, or to add the missing operations to an existing resource. The steps are in order. Do not skip the verification at the end.

## 1. Inventory the operations

```bash
bin/spec ops --tag=<tag>          # e.g. secrets, containers, images, pods, networks, volumes, …
bin/spec coverage -v              # what is implemented / deferred / missing for that tag
```

Skip any operation listed in `dev-tools/deferred-operations.php`, which holds the streaming and hijacked endpoints. Only add an operation to that file, with a reason, when it genuinely needs streaming support.

## 2. Read every operation and response shape

```bash
bin/spec show <OperationId>       # params (in/type/required/default/enum), responses, produces
bin/spec show <Definition>        # properties; "⚠ degraded" means the shape came from an older spec
```

`show` also prints the following:
- the properties of inline body and response schemas (`inline schema (body …)`)
- `[since X.Y]` on parameters that are newer than the operation
- `alias of X` for pure `$ref` alias definitions

> **Degraded-spec trap.** When the newest spec's response type is degraded, also run
> `bin/spec show <OperationId> --spec=<previous>`. If the response definition *name* changed between
> versions, the old name is the one that describes the real wire shape. The resolver's same-name match
> can be wrong. For example, v5.8 `SystemVersion` says `ComponentVersion`, but the server returns v5.7
> `SystemComponentVersion`.
>
> **Go `error` fields** (`Err`, `Error` in `*Report` types) are serialised as `{}` when Podman forgets to
> stringify them. Read them with `Data::errorOrNull()`, never `stringOrNull()`.

For each operation, record these things:
- **Path params**, which become required `string` arguments.
- **Query params**:
  - Up to about 3 simple ones become named method arguments.
  - Beyond that, use a `{Resource}{Action}Options` object that implements `QueryParameters`.
- **The body param**, which becomes a `{Resource}{Action}Request` (or `…Spec`) that implements `RequestBody`, or a stream or string for tar or plain payloads.
- **Header params**, for example `X-Registry-Auth`, which becomes a `?RegistryAuth $auth` argument.
- **The success code(s)**, which decide the return type:

  | Response | Return |
  |---|---|
  | JSON object | DTO |
  | JSON array | `list<Dto>` (via `Data::listOf`) |
  | 204, no body | `void` |
  | 204/404 `exists` | `bool` via `$this->probe()` |
  | 204/304 state change | `bool` (`! $result->isNotModified()`) |
  | `text/plain` | `string` |
  | `application/x-tar` / `octet-stream` | `StreamInterface` |
  | Progress stream, single object vs array mismatches, `{Id}`-only bodies | see "Response shapes the spec gets wrong" in [conventions](../reference/conventions.md) |

## 3. Scaffold, then curate by hand

```bash
bin/spec scaffold resource <tag> <ClassName>          # stubs with every param/response as @todo
bin/spec scaffold dto <Definition> <Namespace> [Class] # e.g. scaffold dto ListContainer Container ContainerSummary
```

Use the scaffold output as raw material, never commit it verbatim. Curate it as follows:
- **Rename** classes and methods per [conventions](../reference/conventions.md#naming).
- **Tighten identity fields** (`Id`, `Name`) to non-null `Data::string()`. Leave the other fields nullable.
- **Model nested objects.** Give a nested object its own DTO when callers will use its fields. Otherwise keep it as `array<string, mixed>` with a docblock.
- **Watch for unbounded shapes.** Treat Go structs with dozens of fields (`SpecGenerator`, `InspectContainerData.HostConfig`, …) per the conventions on large shapes.
- **Copy the spec description** into a one-line docblock per property where it adds meaning.

## 4. Write the resource

- **Location:** `src/Resources/<ClassName>.php`, declared `final readonly class … extends AbstractResource`.
- **Code shape:** follow [templates.md](../reference/templates.md) exactly. Each method gets:
  - a docblock with a one-line summary
  - the `@return list<…>` type where it applies
  - `@throws NotFoundException` and friends where the spec lists them
- **Version-gated operations:** if `bin/spec ops` shows `since` > 5.4, add `@since Podman X.Y` to the method docblock. The transport enforces the gate itself.

## 5. Register the accessor on PodmanClient

Add a `private <ClassName> $<name>;` property, assign it in the constructor, and add `public function <name>(): <ClassName>`. Keep the accessors alphabetical.

## 6. Tests

- **Fixtures:** `tests/Fixtures/responses/<resource>/<case>.json`. Each one must match the spec definition: use the spec's JSON keys and realistic values.
- **Unit tests:** `tests/Unit/Resources/<ClassName>Test.php`, one `it()` per public method, asserting:
  - the request: method plus `lastTarget()` (path and query), and `lastJsonBody()` or headers where relevant
  - the hydrated result: types and key fields
  - at least one error path per resource, such as 404 → `NotFoundException`
  - for gated operations, `UnsupportedApiVersionException` under `MockPodman::withVersion(ApiVersion::V5_4)`
- **Integration tests:** `tests/Integration/<ClassName>Test.php`. Write a create → inspect → list → remove round-trip.
  - Get the client with `integrationClient()` from `tests/Pest.php`. It skips without `PODMAN_SOCKET` and targets the server's own API version, so version-gated operations work.
  - Call `ensureTestImage($this->podman)` when you need `PODMAN_TEST_IMAGE`.
  - Always clean up in `afterEach`/`finally`.
- **Test constants:** Pest loads every test file into one process. File-level `const` names must carry a per-file prefix (`IMAGES_TEST_…`), or use `$this->…` set in `beforeEach`.
- **`lastTarget()`** URL-decodes only the query. Path segments stay encoded (`/libpod/images/quay.io%2Flibpod%2Falpine%3Alatest/json`).

## 7. Verify

```bash
composer test            # must be green: Pest unit + arch + PHPStan max
bin/spec coverage        # implemented count went up by the operations you added; no new "missing" for this tag
PODMAN_SOCKET=… composer test:integration   # when a socket is available
```

If any step revealed a gap in this workflow or the templates, update this file in the same change.
