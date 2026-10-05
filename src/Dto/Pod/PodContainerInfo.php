<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A container of a pod, as embedded in the pod inspect response.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/InspectPodContainerInfo
 */
final readonly class PodContainerInfo implements Hydratable
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        /** Current container state, e.g. "running" */
        public ?string $state = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            name: Data::stringOrNull($data, 'Name'),
            state: Data::stringOrNull($data, 'State'),
        );
    }
}
