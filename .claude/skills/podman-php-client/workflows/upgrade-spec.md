# Workflow: upgrade to a new Podman spec version

Use this when a new `swagger-vX.Y.yaml` is published, from Podman's `pkg/api/swagger.yaml` or the release docs.

## 1. Add the spec

```bash
cp ~/Downloads/swagger-latest.yaml resources/podman/swagger-vX.Y.yaml   # file name must be swagger-v<major>.<minor>.yaml
bin/spec versions                                                      # confirm it is listed last
```

## 2. Add the ApiVersion case

In `src/Enums/ApiVersion.php`, append `case VX_Y = 'X.Y.0';` in ascending order. `latest()` then picks it up automatically, which also makes it the default `ClientConfig::$apiVersion`.

## 3. Diff against the previous version

```bash
bin/spec diff <prev> X.Y            # Libpod surface (reachable definitions only)
bin/spec diff <prev> X.Y --all      # include compat endpoints, for curiosity only
bin/spec degraded                   # shapeless definitions in the new spec
```

Save the diff output in your notes. Every line needs a decision in step 5.

## 4. Regenerate the operation registry

```bash
bin/spec operation-enum
git diff src/Internal/Operation.php
```

The generator refuses to run if an `ApiVersion` case is missing. New operations get `since()` = X.Y automatically.

## 5. Triage every change

| Diff section | Action |
|---|---|
| Added operations | Implement each one via [add-resource.md](add-resource.md), or add it to `dev-tools/deferred-operations.php` with a reason. Add `@since Podman X.Y` to the method docblock. |
| Removed operations | **Breaking.** Do not delete the PHP method silently. Keep it if older servers still need it, and note it in CHANGELOG. If the route is truly gone, plan a major release. |
| Changed operations, `+ param` | Add the parameter to the options object or method. Document it with `@since Podman X.Y` (Podman ignores unknown query params on older servers). |
| Changed operations, `- param` / `~ param` | Check whether the PHP side sends it. Renames such as `Ignore` → `ignore` need the wire name updated. |
| Changed operations, `~ response` | Compare the old and new shapes with `bin/spec show <Def> --spec=<prev>` and `--spec=X.Y`. A rename alone means nothing to do. A shape change means updating the DTO. |
| Added/removed/changed definitions | Update each affected DTO's `fromArray()` and constructor. New fields are nullable. **Do not drop removed fields** while older supported versions still send them; keep them nullable. |
| Degraded definitions | Shapes resolve to the older spec automatically. Keep the DTO's `@see` pointing at the version the shape came from. Never strip fields just because the new spec lost them. |

## 6. Verify

```bash
bin/spec operation-enum --check
composer test
bin/spec coverage -v                   # no unexpected "missing"
PODMAN_SOCKET=… composer test:integration   # against a Podman X.Y server, if available
```

## 7. Record it

- **CHANGELOG.md:** under `Unreleased`, add "Support Podman API X.Y" plus the user-visible additions, deprecations and breaks.
- **README:** update the supported-versions line if it lists versions.
- **CLAUDE.md:** update the "Spec quirks" section if the new spec has new quirks, for example degraded definitions.
