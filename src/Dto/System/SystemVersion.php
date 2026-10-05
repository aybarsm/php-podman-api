<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Server version information (GET /libpod/version).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SystemComponentVersion (v5.8 references a degraded ComponentVersion)
 */
final readonly class SystemVersion implements Hydratable
{
    /**
     * @param list<ComponentVersion> $components
     */
    public function __construct(
        public string $version,
        public ?string $apiVersion = null,
        public ?string $minApiVersion = null,
        public ?string $platformName = null,
        public array $components = [],
        public ?string $os = null,
        public ?string $arch = null,
        public ?string $kernelVersion = null,
        public ?string $goVersion = null,
        public ?string $gitCommit = null,
        public ?string $buildTime = null,
        public bool $experimental = false,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            version: Data::string($data, 'Version'),
            apiVersion: Data::stringOrNull($data, 'ApiVersion'),
            minApiVersion: Data::stringOrNull($data, 'MinAPIVersion'),
            platformName: Data::stringOrNull(Data::map($data, 'Platform'), 'Name'),
            components: Data::objectList($data, 'Components', ComponentVersion::fromArray(...)),
            os: Data::stringOrNull($data, 'Os'),
            arch: Data::stringOrNull($data, 'Arch'),
            kernelVersion: Data::stringOrNull($data, 'KernelVersion'),
            goVersion: Data::stringOrNull($data, 'GoVersion'),
            gitCommit: Data::stringOrNull($data, 'GitCommit'),
            buildTime: Data::stringOrNull($data, 'BuildTime'),
            experimental: Data::bool($data, 'Experimental'),
        );
    }
}
