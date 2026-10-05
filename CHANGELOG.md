# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-10-05

### Added

- Typed client for the Podman **Libpod** REST API (Podman API 5.4–5.8). Its entry point is `PodmanClient`, built with the `unixSocket()`, `tcp()` or `create()` factories. `create()` accepts any PSR-18 client and PSR-17 factories.
- Resources: `system`, `containers`, `images`, `manifests`, `pods`, `networks`, `volumes`, `secrets`, `exec`, `artifacts`, `quadlets`, `kube`.
- Version gating: operations newer than the configured `ApiVersion` throw `UnsupportedApiVersionException` before any request is sent.
- Bounded `Containers::logs()` (multiplexed frames decoded into `LogLine`s) and `Containers::statsAll()` (one sample per container).
- Query-parameter gating (`ParameterGating::Strict` by default, `Off` to disable), generated from the spec.
- Exception hierarchy rooted at `PodmanApiException`. Error responses map to status-specific exceptions that carry Podman's `message` and `cause`.
- Dev tooling (`bin/spec`): spec inspection, diffing, degraded-definition fallback, scaffolding, `Operation` registry generation and coverage.

### Not yet supported

- Streaming and hijacked endpoints: attach, `logs --follow`, stats streaming, events, exec start, and image build.
- Operations whose spec omits a needed body: container/image changes, checkpoint/restore, image resolve, kube down.

See `dev-tools/deferred-operations.php`.
- The Docker-compatible API surface.

[Unreleased]: https://github.com/aybarsm/php-podman-api/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/aybarsm/php-podman-api/releases/tag/v0.1.0
