<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * The host running Podman. Large nested blocks (conmon, OCI runtime, network backend, …) are kept as raw arrays;
 * see `bin/spec show HostInfo` for their shape.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/HostInfo
 */
final readonly class HostInfo implements Hydratable
{
    /**
     * @param list<string>          $cgroupControllers
     * @param list<string>          $emulatedArchitectures
     * @param array<string, mixed>  $distribution        DistributionInfo: codename, distribution, variant, version
     * @param array<string, mixed>  $security            SecurityInfo: rootless, selinuxEnabled, apparmorEnabled, seccompEnabled, …
     * @param array<string, mixed>  $remoteSocket        RemoteSocket: path, exists
     * @param array<string, mixed>  $conmon              ConmonInfo
     * @param array<string, mixed>  $ociRuntime          OCIRuntimeInfo
     * @param array<string, mixed>  $networkBackendInfo  NetworkInfo
     * @param array<string, mixed>  $idMappings          IDMappings
     */
    public function __construct(
        public ?string $hostname = null,
        public ?string $os = null,
        public ?string $arch = null,
        public ?string $variant = null,
        public ?string $kernel = null,
        public ?int $cpus = null,
        public ?int $memTotal = null,
        public ?int $memFree = null,
        public ?int $swapTotal = null,
        public ?int $swapFree = null,
        public ?string $uptime = null,
        public ?string $buildahVersion = null,
        public ?string $cgroupManager = null,
        public ?string $cgroupVersion = null,
        public array $cgroupControllers = [],
        public ?string $databaseBackend = null,
        public ?string $eventLogger = null,
        public ?string $logDriver = null,
        public ?string $networkBackend = null,
        public ?string $rootlessNetworkCmd = null,
        public ?int $freeLocks = null,
        public bool $serviceIsRemote = false,
        public array $emulatedArchitectures = [],
        public array $distribution = [],
        public array $security = [],
        public array $remoteSocket = [],
        public array $conmon = [],
        public array $ociRuntime = [],
        public array $networkBackendInfo = [],
        public array $idMappings = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            hostname: Data::stringOrNull($data, 'hostname'),
            os: Data::stringOrNull($data, 'os'),
            arch: Data::stringOrNull($data, 'arch'),
            variant: Data::stringOrNull($data, 'variant'),
            kernel: Data::stringOrNull($data, 'kernel'),
            cpus: Data::intOrNull($data, 'cpus'),
            memTotal: Data::intOrNull($data, 'memTotal'),
            memFree: Data::intOrNull($data, 'memFree'),
            swapTotal: Data::intOrNull($data, 'swapTotal'),
            swapFree: Data::intOrNull($data, 'swapFree'),
            uptime: Data::stringOrNull($data, 'uptime'),
            buildahVersion: Data::stringOrNull($data, 'buildahVersion'),
            cgroupManager: Data::stringOrNull($data, 'cgroupManager'),
            cgroupVersion: Data::stringOrNull($data, 'cgroupVersion'),
            cgroupControllers: Data::stringList($data, 'cgroupControllers'),
            databaseBackend: Data::stringOrNull($data, 'databaseBackend'),
            eventLogger: Data::stringOrNull($data, 'eventLogger'),
            logDriver: Data::stringOrNull($data, 'logDriver'),
            networkBackend: Data::stringOrNull($data, 'networkBackend'),
            rootlessNetworkCmd: Data::stringOrNull($data, 'rootlessNetworkCmd'),
            freeLocks: Data::intOrNull($data, 'freeLocks'),
            serviceIsRemote: Data::bool($data, 'serviceIsRemote'),
            emulatedArchitectures: Data::stringList($data, 'emulatedArchitectures'),
            distribution: Data::map($data, 'distribution'),
            security: Data::map($data, 'security'),
            remoteSocket: Data::map($data, 'remoteSocket'),
            conmon: Data::map($data, 'conmon'),
            ociRuntime: Data::map($data, 'ociRuntime'),
            networkBackendInfo: Data::map($data, 'networkBackendInfo'),
            idMappings: Data::map($data, 'idMappings'),
        );
    }

    public function isRootless(): bool
    {
        return ($this->security['rootless'] ?? false) === true;
    }
}
