<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * One resource-usage sample for a running container.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/ContainerStats
 */
final readonly class ContainerStats implements Hydratable
{
    /**
     * @param array<string, array<string, mixed>> $network interface name → ContainerNetworkStats (RxBytes, TxBytes, …)
     */
    public function __construct(
        public string $containerId,
        public ?string $name = null,
        /** CPU usage in percent since the previous sample */
        public ?float $cpu = null,
        /** Average CPU usage in percent over the container's lifetime */
        public ?float $avgCpu = null,
        public ?int $cpuNano = null,
        public ?int $cpuSystemNano = null,
        public ?int $systemNano = null,
        public ?int $memUsage = null,
        public ?int $memLimit = null,
        public ?float $memPerc = null,
        public ?int $blockInput = null,
        public ?int $blockOutput = null,
        public ?int $pids = null,
        /** Nanoseconds (Go time.Duration) */
        public ?int $upTime = null,
        /** Nanoseconds (Go time.Duration) */
        public ?int $duration = null,
        public array $network = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        $network = [];
        foreach (Data::map($data, 'Network') as $interface => $stats) {
            $network[$interface] = Data::map([$interface => $stats], $interface);
        }

        return new self(
            containerId: Data::string($data, 'ContainerID'),
            name: Data::stringOrNull($data, 'Name'),
            cpu: Data::floatOrNull($data, 'CPU'),
            avgCpu: Data::floatOrNull($data, 'AvgCPU'),
            cpuNano: Data::intOrNull($data, 'CPUNano'),
            cpuSystemNano: Data::intOrNull($data, 'CPUSystemNano'),
            systemNano: Data::intOrNull($data, 'SystemNano'),
            memUsage: Data::intOrNull($data, 'MemUsage'),
            memLimit: Data::intOrNull($data, 'MemLimit'),
            memPerc: Data::floatOrNull($data, 'MemPerc'),
            blockInput: Data::intOrNull($data, 'BlockInput'),
            blockOutput: Data::intOrNull($data, 'BlockOutput'),
            pids: Data::intOrNull($data, 'PIDs'),
            upTime: Data::intOrNull($data, 'UpTime'),
            duration: Data::intOrNull($data, 'Duration'),
            network: $network,
        );
    }
}
