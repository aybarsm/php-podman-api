<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * Full container configuration and state (`podman container inspect`).
 *
 * Config, HostConfig, NetworkSettings, GraphDriver and Mounts are large Docker-compatible structures kept as raw
 * arrays; see `bin/spec show InspectContainerConfig` / `InspectContainerHostConfig` / `InspectNetworkSettings`.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/InspectContainerData
 */
final readonly class ContainerInspect implements Hydratable
{
    /**
     * @param list<string>               $args
     * @param list<string>               $dependencies
     * @param list<string>               $execIds
     * @param list<string>               $effectiveCaps
     * @param list<string>               $boundingCaps
     * @param array<string, mixed>       $config          InspectContainerConfig (Env, Labels, Cmd, Entrypoint, …)
     * @param array<string, mixed>       $hostConfig      InspectContainerHostConfig
     * @param array<string, mixed>       $networkSettings InspectNetworkSettings
     * @param array<string, mixed>       $graphDriver     DriverData
     * @param list<array<string, mixed>> $mounts          InspectMount
     */
    public function __construct(
        public string $id,
        public string $name,
        public ContainerInspectState $state,
        public ?DateTimeImmutable $created = null,
        public ?string $path = null,
        public array $args = [],
        public ?string $image = null,
        public ?string $imageName = null,
        public ?string $imageDigest = null,
        public ?string $pod = null,
        public ?int $restartCount = null,
        public ?string $driver = null,
        public ?string $ociRuntime = null,
        public ?string $namespace = null,
        public bool $isInfra = false,
        public bool $isService = false,
        public ?int $sizeRootFs = null,
        public ?int $sizeRw = null,
        public array $dependencies = [],
        public array $execIds = [],
        public array $effectiveCaps = [],
        public array $boundingCaps = [],
        public ?string $mountLabel = null,
        public ?string $processLabel = null,
        public ?string $appArmorProfile = null,
        public ?string $rootfs = null,
        public ?string $staticDir = null,
        public ?string $ociConfigPath = null,
        public ?string $conmonPidFile = null,
        public ?string $pidFile = null,
        public ?string $hostnamePath = null,
        public ?string $hostsPath = null,
        public ?string $resolvConfPath = null,
        public ?string $kubeExitCodePropagation = null,
        public ?int $lockNumber = null,
        public array $config = [],
        public array $hostConfig = [],
        public array $networkSettings = [],
        public array $graphDriver = [],
        public array $mounts = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            name: Data::string($data, 'Name'),
            state: Data::object($data, 'State', ContainerInspectState::fromArray(...)),
            created: Data::dateTimeOrNull($data, 'Created'),
            path: Data::stringOrNull($data, 'Path'),
            args: Data::stringList($data, 'Args'),
            image: Data::stringOrNull($data, 'Image'),
            imageName: Data::stringOrNull($data, 'ImageName'),
            imageDigest: Data::stringOrNull($data, 'ImageDigest'),
            pod: Data::stringOrNull($data, 'Pod'),
            restartCount: Data::intOrNull($data, 'RestartCount'),
            driver: Data::stringOrNull($data, 'Driver'),
            ociRuntime: Data::stringOrNull($data, 'OCIRuntime'),
            namespace: Data::stringOrNull($data, 'Namespace'),
            isInfra: Data::bool($data, 'IsInfra'),
            isService: Data::bool($data, 'IsService'),
            sizeRootFs: Data::intOrNull($data, 'SizeRootFs'),
            sizeRw: Data::intOrNull($data, 'SizeRw'),
            dependencies: Data::stringList($data, 'Dependencies'),
            execIds: Data::stringList($data, 'ExecIDs'),
            effectiveCaps: Data::stringList($data, 'EffectiveCaps'),
            boundingCaps: Data::stringList($data, 'BoundingCaps'),
            mountLabel: Data::stringOrNull($data, 'MountLabel'),
            processLabel: Data::stringOrNull($data, 'ProcessLabel'),
            appArmorProfile: Data::stringOrNull($data, 'AppArmorProfile'),
            rootfs: Data::stringOrNull($data, 'Rootfs'),
            staticDir: Data::stringOrNull($data, 'StaticDir'),
            ociConfigPath: Data::stringOrNull($data, 'OCIConfigPath'),
            conmonPidFile: Data::stringOrNull($data, 'ConmonPidFile'),
            pidFile: Data::stringOrNull($data, 'PidFile'),
            hostnamePath: Data::stringOrNull($data, 'HostnamePath'),
            hostsPath: Data::stringOrNull($data, 'HostsPath'),
            resolvConfPath: Data::stringOrNull($data, 'ResolvConfPath'),
            kubeExitCodePropagation: Data::stringOrNull($data, 'KubeExitCodePropagation'),
            lockNumber: Data::intOrNull($data, 'lockNumber'),
            config: Data::map($data, 'Config'),
            hostConfig: Data::map($data, 'HostConfig'),
            networkSettings: Data::map($data, 'NetworkSettings'),
            graphDriver: Data::map($data, 'GraphDriver'),
            mounts: Data::listOf(Data::list($data, 'Mounts'), static fn (array $m): array => $m),
        );
    }

    /**
     * Environment from Config.Env ("KEY=value" strings) as a map.
     *
     * @return array<string, string>
     */
    public function env(): array
    {
        $env = [];
        foreach (Data::stringList($this->config, 'Env') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $env[$key] = $value;
        }

        return $env;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return Data::stringMap($this->config, 'Labels');
    }
}
