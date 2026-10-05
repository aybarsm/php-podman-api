<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Result of removing a pod (`podman pod rm`). Also embedded in KubePlayReport::$removeReports.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/PodRmReport (degraded in v5.8)
 */
final readonly class PodRemoveReport implements Hydratable
{
    /**
     * @param array<string, string|null> $removedContainers container ID → removal error (null when removed cleanly)
     */
    public function __construct(
        public string $id,
        public ?string $error = null,
        public array $removedContainers = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            error: Data::errorOrNull($data, 'Err'),
            // Go map[string]error: container ID → null on success.
            removedContainers: Data::errorMap($data, 'RemovedCtrs'),
        );
    }
}
