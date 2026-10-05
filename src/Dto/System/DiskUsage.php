<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Disk usage by images, containers and volumes (GET /libpod/system/df).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SystemDfReport (degraded in v5.8)
 */
final readonly class DiskUsage implements Hydratable
{
    /**
     * @param list<DiskUsageImage>     $images
     * @param list<DiskUsageContainer> $containers
     * @param list<DiskUsageVolume>    $volumes
     */
    public function __construct(
        public ?int $imagesSize = null,
        public array $images = [],
        public array $containers = [],
        public array $volumes = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            imagesSize: Data::intOrNull($data, 'ImagesSize'),
            images: Data::objectList($data, 'Images', DiskUsageImage::fromArray(...)),
            containers: Data::objectList($data, 'Containers', DiskUsageContainer::fromArray(...)),
            volumes: Data::objectList($data, 'Volumes', DiskUsageVolume::fromArray(...)),
        );
    }
}
