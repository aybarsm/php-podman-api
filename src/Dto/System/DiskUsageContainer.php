<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SystemDfContainerReport (absent in v5.8)
 */
final readonly class DiskUsageContainer implements Hydratable
{
    /**
     * @param list<string> $command
     */
    public function __construct(
        public string $containerId,
        public ?string $names = null,
        public ?string $image = null,
        public array $command = [],
        public ?DateTimeImmutable $created = null,
        public ?string $status = null,
        public ?int $size = null,
        public ?int $rwSize = null,
        public ?int $localVolumes = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            containerId: Data::string($data, 'ContainerID'),
            names: Data::stringOrNull($data, 'Names'),
            image: Data::stringOrNull($data, 'Image'),
            command: Data::stringList($data, 'Command'),
            created: Data::dateTimeOrNull($data, 'Created'),
            status: Data::stringOrNull($data, 'Status'),
            size: Data::intOrNull($data, 'Size'),
            rwSize: Data::intOrNull($data, 'RWSize'),
            localVolumes: Data::intOrNull($data, 'LocalVolumes'),
        );
    }
}
