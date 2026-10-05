<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Aybarsm\Podman\Api\Dto\Container\PortMapping;
use Override;

/**
 * Body for Pods::create() (PodCreateLibpod).
 *
 * PodSpecGenerator has 45+ fields; the common ones are typed here and any other spec field can be passed through
 * $extra using its JSON name (see `bin/spec show PodSpecGenerator`).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/PodSpecGenerator
 */
final readonly class PodCreateSpec implements RequestBody
{
    /**
     * @param array<string, string>|null               $labels
     * @param list<PortMapping>|null                   $portMappings      ports mapped into the infra container
     * @param array<string, array<string, mixed>>|null $networks          network name → PerNetworkOptions
     * @param list<string>|null                        $sharedNamespaces  e.g. ["ipc", "net", "uts"]
     * @param list<string>|null                        $dnsServers
     * @param list<string>|null                        $dnsSearch
     * @param list<string>|null                        $dnsOptions
     * @param list<string>|null                        $hostAdd           extra /etc/hosts entries ("host:ip")
     * @param list<array<string, mixed>>|null          $mounts            spec Mount: Destination, Source, Type, Options
     * @param list<array<string, mixed>>|null          $volumes           spec NamedVolume: Name, Dest, Options
     * @param array<string, mixed>|null                $resourceLimits    spec LinuxResources
     * @param array<string, mixed>                     $extra             any other PodSpecGenerator field, merged last
     */
    public function __construct(
        public ?string $name = null,
        public ?array $labels = null,
        public ?string $hostname = null,
        /** Do not create an infra container */
        public ?bool $noInfra = null,
        public ?string $infraImage = null,
        public ?string $infraName = null,
        public ?array $portMappings = null,
        public ?array $networks = null,
        public ?array $sharedNamespaces = null,
        public ?array $dnsServers = null,
        public ?array $dnsSearch = null,
        public ?array $dnsOptions = null,
        public ?array $hostAdd = null,
        /** Action taken when containers in the pod exit (Podman default: always) */
        public ?string $restartPolicy = null,
        public ?int $restartTries = null,
        /** The pod's exit and stop behaviour */
        public ?string $exitPolicy = null,
        public ?string $cgroupParent = null,
        public ?array $mounts = null,
        public ?array $volumes = null,
        public ?array $resourceLimits = null,
        public array $extra = [],
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'name' => $this->name,
            'labels' => $this->labels,
            'hostname' => $this->hostname,
            'no_infra' => $this->noInfra,
            'infra_image' => $this->infraImage,
            'infra_name' => $this->infraName,
            'portmappings' => $this->portMappings === null
                ? null
                : array_map(static fn (PortMapping $p): array => $p->toArray(), $this->portMappings),
            'Networks' => $this->networks,
            'shared_namespaces' => $this->sharedNamespaces,
            'dns_server' => $this->dnsServers,
            'dns_search' => $this->dnsSearch,
            'dns_option' => $this->dnsOptions,
            'hostadd' => $this->hostAdd,
            'restart_policy' => $this->restartPolicy,
            'restart_tries' => $this->restartTries,
            'exit_policy' => $this->exitPolicy,
            'cgroup_parent' => $this->cgroupParent,
            'mounts' => $this->mounts,
            'volumes' => $this->volumes,
            'resource_limits' => $this->resourceLimits,
            ...$this->extra,
        ];
    }
}
