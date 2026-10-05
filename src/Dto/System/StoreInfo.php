<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Container storage configuration and usage.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/StoreInfo
 */
final readonly class StoreInfo implements Hydratable
{
    /**
     * @param array<string, mixed>  $graphOptions
     * @param array<string, string> $graphStatus
     * @param array<string, mixed>  $containerStore ContainerStore: number, paused, running, stopped
     * @param array<string, mixed>  $imageStore     ImageStore: number
     */
    public function __construct(
        public ?string $graphDriverName = null,
        public ?string $graphRoot = null,
        public ?int $graphRootAllocated = null,
        public ?int $graphRootUsed = null,
        public ?string $runRoot = null,
        public ?string $volumePath = null,
        public ?string $imageCopyTmpDir = null,
        public bool $transientStore = false,
        public array $graphOptions = [],
        public array $graphStatus = [],
        public array $containerStore = [],
        public array $imageStore = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            graphDriverName: Data::stringOrNull($data, 'graphDriverName'),
            graphRoot: Data::stringOrNull($data, 'graphRoot'),
            graphRootAllocated: Data::intOrNull($data, 'graphRootAllocated'),
            graphRootUsed: Data::intOrNull($data, 'graphRootUsed'),
            runRoot: Data::stringOrNull($data, 'runRoot'),
            volumePath: Data::stringOrNull($data, 'volumePath'),
            imageCopyTmpDir: Data::stringOrNull($data, 'imageCopyTmpDir'),
            transientStore: Data::bool($data, 'transientStore'),
            graphOptions: Data::map($data, 'graphOptions'),
            graphStatus: Data::stringMap($data, 'graphStatus'),
            containerStore: Data::map($data, 'containerStore'),
            imageStore: Data::map($data, 'imageStore'),
        );
    }
}
