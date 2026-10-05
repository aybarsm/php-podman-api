<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A container of a pod, as embedded in the pod list response.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ListPodContainer (degraded in v5.8)
 */
final readonly class PodContainerSummary implements Hydratable
{
    public function __construct(
        public string $id,
        /** The container name (a single string despite the plural key) */
        public ?string $name = null,
        public ?string $status = null,
        public ?int $restartCount = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            name: Data::stringOrNull($data, 'Names'),
            status: Data::stringOrNull($data, 'Status'),
            restartCount: Data::intOrNull($data, 'RestartCount'),
        );
    }
}
