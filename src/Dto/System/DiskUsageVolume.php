<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SystemDfVolumeReport (absent in v5.8)
 */
final readonly class DiskUsageVolume implements Hydratable
{
    public function __construct(
        public string $volumeName,
        public ?int $links = null,
        public ?int $size = null,
        public ?int $reclaimableSize = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            volumeName: Data::string($data, 'VolumeName'),
            links: Data::intOrNull($data, 'Links'),
            size: Data::intOrNull($data, 'Size'),
            reclaimableSize: Data::intOrNull($data, 'ReclaimableSize'),
        );
    }
}
