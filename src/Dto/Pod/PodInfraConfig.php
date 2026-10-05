<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Configuration of a pod's infra container.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/InspectPodInfraConfig
 */
final readonly class PodInfraConfig implements Hydratable
{
    /**
     * @param array<string, mixed>        $portBindings   container port ("80/tcp") → list of InspectHostPort {HostIp, HostPort}
     * @param list<string>                $networks       networks the pod joins
     * @param array<string, list<string>> $networkOptions per-network options
     * @param list<string>                $dnsServer
     * @param list<string>                $dnsSearch
     * @param list<string>                $dnsOption
     * @param list<string>                $hostAdd        extra /etc/hosts entries
     */
    public function __construct(
        public array $portBindings = [],
        public bool $hostNetwork = false,
        public ?string $staticIp = null,
        public ?string $staticMac = null,
        public bool $noManageResolvConf = false,
        public bool $noManageHosts = false,
        public bool $noManageHostname = false,
        public ?string $hostsFile = null,
        public array $networks = [],
        public array $networkOptions = [],
        public array $dnsServer = [],
        public array $dnsSearch = [],
        public array $dnsOption = [],
        public array $hostAdd = [],
        public ?string $pidNs = null,
        public ?string $userns = null,
        public ?string $utsNs = null,
        public ?int $cpuPeriod = null,
        public ?int $cpuQuota = null,
        public ?string $cpusetCpus = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            portBindings: Data::map($data, 'PortBindings'),
            hostNetwork: Data::bool($data, 'HostNetwork'),
            staticIp: Data::stringOrNull($data, 'StaticIP'),
            staticMac: Data::stringOrNull($data, 'StaticMAC'),
            noManageResolvConf: Data::bool($data, 'NoManageResolvConf'),
            noManageHosts: Data::bool($data, 'NoManageHosts'),
            noManageHostname: Data::bool($data, 'NoManageHostname'),
            hostsFile: Data::stringOrNull($data, 'HostsFile'),
            networks: Data::stringList($data, 'Networks'),
            networkOptions: Data::stringListMap($data, 'NetworkOptions'),
            dnsServer: Data::stringList($data, 'DNSServer'),
            dnsSearch: Data::stringList($data, 'DNSSearch'),
            dnsOption: Data::stringList($data, 'DNSOption'),
            hostAdd: Data::stringList($data, 'HostAdd'),
            pidNs: Data::stringOrNull($data, 'pid_ns'),
            userns: Data::stringOrNull($data, 'userns'),
            utsNs: Data::stringOrNull($data, 'uts_ns'),
            cpuPeriod: Data::intOrNull($data, 'cpu_period'),
            cpuQuota: Data::intOrNull($data, 'cpu_quota'),
            cpusetCpus: Data::stringOrNull($data, 'cpuset_cpus'),
        );
    }
}
