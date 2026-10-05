<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Dto\Network\NetworkPruneReport;
use Aybarsm\Podman\Api\Dto\Pod\PodPruneReport;
use Aybarsm\Podman\Api\Dto\Shared\PruneReport;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Result of POST /libpod/system/prune.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SystemPruneReport (degraded in v5.8)
 */
final readonly class SystemPruneReport implements Hydratable
{
    /**
     * @param list<PodPruneReport>     $pods
     * @param list<PruneReport>        $containers
     * @param list<PruneReport>        $images
     * @param list<NetworkPruneReport> $networks
     * @param list<PruneReport>        $volumes
     */
    public function __construct(
        public array $pods = [],
        public array $containers = [],
        public array $images = [],
        public array $networks = [],
        public array $volumes = [],
        public ?int $reclaimedSpace = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            pods: Data::objectList($data, 'PodPruneReport', PodPruneReport::fromArray(...)),
            containers: Data::objectList($data, 'ContainerPruneReports', PruneReport::fromArray(...)),
            images: Data::objectList($data, 'ImagePruneReports', PruneReport::fromArray(...)),
            networks: Data::objectList($data, 'NetworkPruneReports', NetworkPruneReport::fromArray(...)),
            volumes: Data::objectList($data, 'VolumePruneReports', PruneReport::fromArray(...)),
            reclaimedSpace: Data::intOrNull($data, 'ReclaimedSpace'),
        );
    }
}
