<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Enums\PodStatus;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * Detailed pod configuration and state (`podman pod inspect`).
 *
 * Device and blkio limit lists are kept as raw arrays; see `bin/spec show InspectPodData` for their shapes.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/InspectPodData
 */
final readonly class PodInspect implements Hydratable
{
    /**
     * @param list<string>               $createCommand    full command line that created the pod
     * @param array<string, string>      $labels
     * @param list<string>               $sharedNamespaces e.g. ["ipc", "net", "uts"]
     * @param list<PodContainerInfo>     $containers
     * @param list<array<string, mixed>> $mounts           spec InspectMount
     * @param list<array<string, mixed>> $devices          spec InspectDevice
     * @param list<array<string, mixed>> $blkioWeightDevice spec InspectBlkioWeightDevice
     * @param list<array<string, mixed>> $deviceReadBps    spec InspectBlkioThrottleDevice
     * @param list<array<string, mixed>> $deviceWriteBps   spec InspectBlkioThrottleDevice
     * @param list<string>               $securityOpt
     * @param list<string>               $volumesFrom
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $namespace = null,
        public ?DateTimeImmutable $created = null,
        public array $createCommand = [],
        /** Raw state, e.g. "Running"; see knownState() */
        public ?string $state = null,
        public ?string $exitPolicy = null,
        public ?string $restartPolicy = null,
        public ?string $hostname = null,
        public array $labels = [],
        public bool $createCgroup = false,
        public ?string $cgroupParent = null,
        public ?string $cgroupPath = null,
        public bool $createInfra = false,
        public ?string $infraContainerId = null,
        public ?PodInfraConfig $infraConfig = null,
        public array $sharedNamespaces = [],
        /** Number of containers in the pod, including the infra container */
        public ?int $numContainers = null,
        public array $containers = [],
        public ?int $lockNumber = null,
        public ?int $cpuPeriod = null,
        public ?int $cpuQuota = null,
        public ?int $cpuShares = null,
        public ?string $cpusetCpus = null,
        public ?string $cpusetMems = null,
        public ?int $memoryLimit = null,
        public ?int $memorySwap = null,
        public ?int $blkioWeight = null,
        public array $mounts = [],
        public array $devices = [],
        public array $blkioWeightDevice = [],
        public array $deviceReadBps = [],
        public array $deviceWriteBps = [],
        public array $securityOpt = [],
        public array $volumesFrom = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            name: Data::string($data, 'Name'),
            namespace: Data::stringOrNull($data, 'Namespace'),
            created: Data::dateTimeOrNull($data, 'Created'),
            createCommand: Data::stringList($data, 'CreateCommand'),
            state: Data::stringOrNull($data, 'State'),
            exitPolicy: Data::stringOrNull($data, 'ExitPolicy'),
            restartPolicy: Data::stringOrNull($data, 'RestartPolicy'),
            hostname: Data::stringOrNull($data, 'Hostname'),
            labels: Data::stringMap($data, 'Labels'),
            createCgroup: Data::bool($data, 'CreateCgroup'),
            cgroupParent: Data::stringOrNull($data, 'CgroupParent'),
            cgroupPath: Data::stringOrNull($data, 'CgroupPath'),
            createInfra: Data::bool($data, 'CreateInfra'),
            infraContainerId: Data::stringOrNull($data, 'InfraContainerID'),
            infraConfig: Data::objectOrNull($data, 'InfraConfig', PodInfraConfig::fromArray(...)),
            sharedNamespaces: Data::stringList($data, 'SharedNamespaces'),
            numContainers: Data::intOrNull($data, 'NumContainers'),
            containers: Data::objectList($data, 'Containers', PodContainerInfo::fromArray(...)),
            lockNumber: Data::intOrNull($data, 'LockNumber'),
            cpuPeriod: Data::intOrNull($data, 'cpu_period'),
            cpuQuota: Data::intOrNull($data, 'cpu_quota'),
            cpuShares: Data::intOrNull($data, 'cpu_shares'),
            cpusetCpus: Data::stringOrNull($data, 'cpuset_cpus'),
            cpusetMems: Data::stringOrNull($data, 'cpuset_mems'),
            memoryLimit: Data::intOrNull($data, 'memory_limit'),
            memorySwap: Data::intOrNull($data, 'memory_swap'),
            blkioWeight: Data::intOrNull($data, 'blkio_weight'),
            mounts: self::objects($data, 'mounts'),
            devices: self::objects($data, 'devices'),
            blkioWeightDevice: self::objects($data, 'blkio_weight_device'),
            deviceReadBps: self::objects($data, 'device_read_bps'),
            deviceWriteBps: self::objects($data, 'device_write_bps'),
            securityOpt: Data::stringList($data, 'security_opt'),
            volumesFrom: Data::stringList($data, 'volumes_from'),
        );
    }

    public function knownState(): ?PodStatus
    {
        return PodStatus::fromResponse($this->state);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private static function objects(array $data, string $key): array
    {
        return Data::objectList($data, $key, static fn (array $item): array => $item);
    }
}
