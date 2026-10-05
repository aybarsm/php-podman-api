<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * Podman build/version block embedded in SystemInfo.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/Version
 */
final readonly class VersionInfo implements Hydratable
{
    public function __construct(
        public string $version,
        public ?string $apiVersion = null,
        public ?string $goVersion = null,
        public ?string $gitCommit = null,
        public ?DateTimeImmutable $built = null,
        public ?string $builtTime = null,
        public ?string $buildOrigin = null,
        public ?string $osArch = null,
        public ?string $os = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            version: Data::string($data, 'Version'),
            apiVersion: Data::stringOrNull($data, 'APIVersion'),
            goVersion: Data::stringOrNull($data, 'GoVersion'),
            gitCommit: Data::stringOrNull($data, 'GitCommit'),
            built: Data::dateTimeOrNull($data, 'Built'),
            builtTime: Data::stringOrNull($data, 'BuiltTime'),
            buildOrigin: Data::stringOrNull($data, 'BuildOrigin'),
            osArch: Data::stringOrNull($data, 'OsArch'),
            os: Data::stringOrNull($data, 'Os'),
        );
    }
}
