<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Kube;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A pod created by `podman kube play`.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/PlayKubePod (degraded in v5.8)
 */
final readonly class KubePlayPod implements Hydratable
{
    /**
     * @param list<string> $containers      IDs of the containers running in the pod
     * @param list<string> $initContainers  IDs of the init containers
     * @param list<string> $containerErrors errors that occurred while starting containers
     * @param list<string> $logs            non-fatal errors and log messages
     */
    public function __construct(
        public string $id,
        public array $containers = [],
        public array $initContainers = [],
        public array $containerErrors = [],
        public array $logs = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'ID'),
            containers: Data::stringList($data, 'Containers'),
            initContainers: Data::stringList($data, 'InitContainers'),
            containerErrors: Data::stringList($data, 'ContainerErrors'),
            logs: Data::stringList($data, 'Logs'),
        );
    }
}
