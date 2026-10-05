<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Storage consistency check findings, and what was removed when repairing (POST /libpod/system/check).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SystemCheckReport (degraded in v5.8)
 */
final readonly class StorageCheckReport implements Hydratable
{
    /**
     * @param array<string, list<string>> $layers            layer ID → problems
     * @param array<string, list<string>> $roLayers          read-only layer ID → problems
     * @param array<string, list<string>> $images            image ID → problems
     * @param array<string, list<string>> $roImages          read-only image ID → problems
     * @param array<string, list<string>> $containers        container ID → problems
     * @param list<string>                $removedLayers
     * @param array<string, list<string>> $removedImages     image ID → names
     * @param array<string, string>       $removedContainers container ID → name
     */
    public function __construct(
        public bool $errors = false,
        public array $layers = [],
        public array $roLayers = [],
        public array $images = [],
        public array $roImages = [],
        public array $containers = [],
        public array $removedLayers = [],
        public array $removedImages = [],
        public array $removedContainers = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            errors: Data::bool($data, 'Errors'),
            layers: Data::stringListMap($data, 'Layers'),
            roLayers: Data::stringListMap($data, 'ROLayers'),
            images: Data::stringListMap($data, 'Images'),
            roImages: Data::stringListMap($data, 'ROImages'),
            containers: Data::stringListMap($data, 'Containers'),
            removedLayers: Data::stringList($data, 'RemovedLayers'),
            removedImages: Data::stringListMap($data, 'RemovedImages'),
            removedContainers: Data::stringMap($data, 'RemovedContainers'),
        );
    }
}
