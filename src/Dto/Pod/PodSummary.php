<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Enums\PodStatus;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * A pod as returned by the list endpoint (`podman pod ps`).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ListPodsReport (degraded in v5.8)
 */
final readonly class PodSummary implements Hydratable
{
    /**
     * @param list<PodContainerSummary> $containers
     * @param array<string, string>     $labels
     * @param list<string>              $networks   network names connected to the infra container
     */
    public function __construct(
        public string $id,
        public string $name,
        /** Raw status, e.g. "Running"; see knownStatus() */
        public ?string $status = null,
        public ?DateTimeImmutable $created = null,
        public ?string $cgroup = null,
        public ?string $infraId = null,
        /** Libpod namespace */
        public ?string $namespace = null,
        public array $containers = [],
        public array $labels = [],
        public array $networks = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            name: Data::string($data, 'Name'),
            status: Data::stringOrNull($data, 'Status'),
            created: Data::dateTimeOrNull($data, 'Created'),
            cgroup: Data::stringOrNull($data, 'Cgroup'),
            infraId: Data::stringOrNull($data, 'InfraId'),
            namespace: Data::stringOrNull($data, 'Namespace'),
            containers: Data::objectList($data, 'Containers', PodContainerSummary::fromArray(...)),
            labels: Data::stringMap($data, 'Labels'),
            networks: Data::stringList($data, 'Networks'),
        );
    }

    public function knownStatus(): ?PodStatus
    {
        return PodStatus::fromResponse($this->status);
    }
}
