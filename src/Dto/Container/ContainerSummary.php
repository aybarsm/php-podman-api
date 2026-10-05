<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Enums\ContainerState;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * A container as returned by the list endpoint.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ListContainer (degraded in v5.8)
 */
final readonly class ContainerSummary implements Hydratable
{
    /**
     * @param list<string>          $names
     * @param list<string>          $command
     * @param array<string, string> $labels
     * @param list<string>          $mounts       user volume mount destinations
     * @param list<string>          $networks     network names
     * @param list<PortMapping>     $ports
     * @param array<string, mixed>  $exposedPorts ports exposed but not forwarded
     * @param array<string, string> $namespaces   Cgroup, Ipc, Mnt, Net, Pidns, User, Uts (only with `namespace=true`)
     */
    public function __construct(
        public string $id,
        public array $names = [],
        public ?string $image = null,
        public ?string $imageId = null,
        public array $command = [],
        public ?DateTimeImmutable $created = null,
        public ?string $createdAt = null,
        /** Raw state; see knownState() */
        public ?string $state = null,
        /** Human-readable status, e.g. "Up 2 minutes" */
        public ?string $status = null,
        public bool $exited = false,
        public ?int $exitCode = null,
        public ?DateTimeImmutable $exitedAt = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?int $pid = null,
        public ?string $pod = null,
        public ?string $podName = null,
        public bool $isInfra = false,
        public bool $autoRemove = false,
        public ?int $restarts = null,
        public array $labels = [],
        public array $mounts = [],
        public array $networks = [],
        public array $ports = [],
        public array $exposedPorts = [],
        public array $namespaces = [],
        /** Only with `size=true` */
        public ?int $sizeRootFs = null,
        /** Only with `size=true` */
        public ?int $sizeRw = null,
        public ?string $cidFile = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        $size = Data::map($data, 'Size');

        return new self(
            id: Data::string($data, 'Id'),
            names: Data::stringList($data, 'Names'),
            image: Data::stringOrNull($data, 'Image'),
            imageId: Data::stringOrNull($data, 'ImageID'),
            command: Data::stringList($data, 'Command'),
            created: Data::dateTimeOrNull($data, 'Created'),
            createdAt: Data::stringOrNull($data, 'CreatedAt'),
            state: Data::stringOrNull($data, 'State'),
            status: Data::stringOrNull($data, 'Status'),
            exited: Data::bool($data, 'Exited'),
            exitCode: Data::intOrNull($data, 'ExitCode'),
            exitedAt: self::unixTime($data, 'ExitedAt'),
            startedAt: self::unixTime($data, 'StartedAt'),
            pid: Data::intOrNull($data, 'Pid'),
            pod: Data::stringOrNull($data, 'Pod'),
            podName: Data::stringOrNull($data, 'PodName'),
            isInfra: Data::bool($data, 'IsInfra'),
            autoRemove: Data::bool($data, 'AutoRemove'),
            restarts: Data::intOrNull($data, 'Restarts'),
            labels: Data::stringMap($data, 'Labels'),
            mounts: Data::stringList($data, 'Mounts'),
            networks: Data::stringList($data, 'Networks'),
            ports: Data::objectList($data, 'Ports', PortMapping::fromArray(...)),
            exposedPorts: Data::map($data, 'ExposedPorts'),
            namespaces: Data::stringMap($data, 'Namespaces'),
            sizeRootFs: Data::intOrNull($size, 'rootFsSize'),
            sizeRw: Data::intOrNull($size, 'rwSize'),
            cidFile: Data::stringOrNull($data, 'CIDFile'),
        );
    }

    public function knownState(): ?ContainerState
    {
        return $this->state === null ? null : ContainerState::tryFrom($this->state);
    }

    public function name(): ?string
    {
        return $this->names[0] ?? null;
    }

    /**
     * ExitedAt/StartedAt are unix seconds; Podman reports 0 or negative values when the event never happened.
     *
     * @param array<string, mixed> $data
     */
    private static function unixTime(array $data, string $key): ?DateTimeImmutable
    {
        $ts = Data::intOrNull($data, $key);

        return $ts === null || $ts <= 0 ? null : Data::dateTimeOrNull([$key => $ts], $key);
    }
}
