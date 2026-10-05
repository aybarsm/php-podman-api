---
name: podman-php-client
description: Workflows and templates for the aybarsm/podman-api PHP client. Use when adding or extending a Podman resource (containers, images, pods, networks, volumes, secrets, manifests, exec, system, artifacts, quadlets, kube), adding an endpoint, DTO or option object, fixing hydration against the swagger spec, or upgrading to a new Podman swagger version (new resources/podman/swagger-vX.Y.yaml).
---

# Podman PHP client

`resources/podman/swagger-v*.yaml` is the only source of truth. Every endpoint, parameter and field must trace back to it through `bin/spec show`. Read `CLAUDE.md` first for the architecture and the conventions the tests enforce.

## Pick the workflow

| Task | Follow |
|---|---|
| Implement a resource or add operations to an existing one | [workflows/add-resource.md](workflows/add-resource.md) |
| A new Podman release adds `swagger-vX.Y.yaml` | [workflows/upgrade-spec.md](workflows/upgrade-spec.md) |
| You need the exact code shape for a resource method, DTO, option object or test | [reference/templates.md](reference/templates.md) |
| You are mapping a spec type to PHP, choosing names, or handling a spec quirk | [reference/conventions.md](reference/conventions.md) |

## Always

- **Find operations with** `bin/spec ops --tag=<tag>`, and **read the details with** `bin/spec show <OperationId>` or `bin/spec show <Definition>`. If `show` prints "⚠ degraded", the shape comes from an older spec. Cite that version in the DTO's `@see`.
- **Each public resource method calls `$this->transport->send(Operation::<Case>, …)` exactly once.** It never returns `Result` or `ResponseInterface`.
- **Finish every change with** `composer test` (Pest + arch + PHPStan max) and `bin/spec coverage`.
- **Never edit `src/Internal/Operation.php` by hand.** Run `bin/spec operation-enum` instead.
- **Never weaken an arch test or add a `@phpstan-ignore` to get green.** Fix the code instead.
