<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Host, storage and version information (GET /libpod/info).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/LibpodInfo
 */
final readonly class SystemInfo implements Hydratable
{
    /**
     * @param array<string, mixed> $registries search registries and per-registry config
     * @param array<string, mixed> $plugins    Plugins: authorization, log, network, volume
     */
    public function __construct(
        public HostInfo $host,
        public StoreInfo $store,
        public VersionInfo $version,
        public array $registries = [],
        public array $plugins = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            host: Data::object($data, 'host', HostInfo::fromArray(...)),
            store: Data::object($data, 'store', StoreInfo::fromArray(...)),
            version: Data::object($data, 'version', VersionInfo::fromArray(...)),
            registries: Data::map($data, 'registries'),
            plugins: Data::map($data, 'plugins'),
        );
    }
}
