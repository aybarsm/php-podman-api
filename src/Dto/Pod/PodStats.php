<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Resource usage of one container of a pod (`podman pod stats`). All values are pre-formatted strings.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/PodStatsReport (degraded in v5.8)
 */
final readonly class PodStats implements Hydratable
{
    public function __construct(
        /** Pod ID */
        public string $pod,
        /** Container ID */
        public string $containerId,
        /** Pod name */
        public ?string $name = null,
        /** CPU usage percentage, e.g. "0.12%" */
        public ?string $cpu = null,
        /** Memory usage percentage */
        public ?string $mem = null,
        /** Humanised usage / limit, e.g. "1.2MB / 8GB" */
        public ?string $memUsage = null,
        /** Usage / limit in bytes */
        public ?string $memUsageBytes = null,
        /** Network in / out */
        public ?string $netIo = null,
        /** Block device read / write */
        public ?string $blockIo = null,
        /** Number of PIDs */
        public ?string $pids = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            pod: Data::string($data, 'Pod'),
            containerId: Data::string($data, 'CID'),
            name: Data::stringOrNull($data, 'Name'),
            cpu: Data::stringOrNull($data, 'CPU'),
            mem: Data::stringOrNull($data, 'Mem'),
            memUsage: Data::stringOrNull($data, 'MemUsage'),
            memUsageBytes: Data::stringOrNull($data, 'MemUsageBytes'),
            netIo: Data::stringOrNull($data, 'NetIO'),
            blockIo: Data::stringOrNull($data, 'BlockIO'),
            pids: Data::stringOrNull($data, 'PIDS'),
        );
    }
}
