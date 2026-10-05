<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

/**
 * Protocol information returned in the `_ping` response headers.
 *
 * @see resources/podman/swagger-v5.8.yaml#/paths/~1libpod~1_ping
 */
final readonly class Ping
{
    public function __construct(
        /** Max compatibility (Docker) API version the server supports */
        public ?string $apiVersion,
        /** Max Podman API version; present only when the service is backed by Podman */
        public ?string $libpodApiVersion,
        /** Default libpod image builder version */
        public ?string $buildahVersion,
        public ?string $buildkitVersion,
        public bool $experimental,
    ) {}

    /**
     * @param array<string, list<string>> $headers lower-cased header names
     */
    public static function fromHeaders(array $headers): self
    {
        $h = static fn (string $name): ?string => $headers[$name][0] ?? null;

        return new self(
            apiVersion: $h('api-version'),
            libpodApiVersion: $h('libpod-api-version'),
            buildahVersion: $h('libpod-buildah-version'),
            buildkitVersion: $h('buildkit-version'),
            experimental: $h('docker-experimental') === 'true',
        );
    }

    public function isPodman(): bool
    {
        return $this->libpodApiVersion !== null;
    }
}
