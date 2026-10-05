<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for Containers::create() (ContainerCreateLibpod).
 *
 * SpecGenerator has 120+ fields; the common ones are typed here and any other spec field can be passed through
 * $extra using its JSON name (see `bin/spec show SpecGenerator`).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/SpecGenerator
 */
final readonly class ContainerCreateSpec implements RequestBody
{
    /**
     * @param list<string>|null                        $command
     * @param list<string>|null                        $entrypoint
     * @param array<string, string>|null               $env
     * @param array<string, string>|null               $labels
     * @param array<string, string>|null               $annotations
     * @param list<PortMapping>|null                   $portMappings
     * @param list<array<string, mixed>>|null          $mounts       spec Mount: Destination, Source, Type, Options
     * @param list<array<string, mixed>>|null          $volumes      spec NamedVolume: Name, Dest, Options
     * @param array<string, array<string, mixed>>|null $networks     network name → PerNetworkOptions
     * @param list<string>|null                        $capAdd
     * @param list<string>|null                        $capDrop
     * @param list<string>|null                        $dnsServers
     * @param array<string, mixed>|null                $healthConfig spec Schema2HealthConfig: Test, Interval, Timeout, Retries, StartPeriod
     * @param array<string, mixed>|null                $resourceLimits spec LinuxResources
     * @param array<string, mixed>                     $extra        any other SpecGenerator field, merged last
     */
    public function __construct(
        public string $image,
        public ?string $name = null,
        public ?array $command = null,
        public ?array $entrypoint = null,
        public ?array $env = null,
        public ?array $labels = null,
        public ?array $annotations = null,
        public ?string $workDir = null,
        public ?string $user = null,
        public ?string $hostname = null,
        /** Pod ID or name to join */
        public ?string $pod = null,
        public ?bool $terminal = null,
        public ?bool $stdin = null,
        /** Remove the container when it exits */
        public ?bool $remove = null,
        public ?bool $privileged = null,
        public ?bool $readOnlyFilesystem = null,
        /** no, always, on-failure, unless-stopped */
        public ?string $restartPolicy = null,
        public ?int $restartTries = null,
        public ?int $stopTimeout = null,
        public ?array $portMappings = null,
        public ?bool $publishImagePorts = null,
        public ?array $mounts = null,
        public ?array $volumes = null,
        public ?array $networks = null,
        public ?array $capAdd = null,
        public ?array $capDrop = null,
        public ?array $dnsServers = null,
        public ?array $healthConfig = null,
        public ?array $resourceLimits = null,
        public array $extra = [],
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'image' => $this->image,
            'name' => $this->name,
            'command' => $this->command,
            'entrypoint' => $this->entrypoint,
            'env' => $this->env,
            'labels' => $this->labels,
            'annotations' => $this->annotations,
            'work_dir' => $this->workDir,
            'user' => $this->user,
            'hostname' => $this->hostname,
            'pod' => $this->pod,
            'terminal' => $this->terminal,
            'stdin' => $this->stdin,
            'remove' => $this->remove,
            'privileged' => $this->privileged,
            'read_only_filesystem' => $this->readOnlyFilesystem,
            'restart_policy' => $this->restartPolicy,
            'restart_tries' => $this->restartTries,
            'stop_timeout' => $this->stopTimeout,
            'portmappings' => $this->portMappings === null
                ? null
                : array_map(static fn (PortMapping $p): array => $p->toArray(), $this->portMappings),
            'publish_image_ports' => $this->publishImagePorts,
            'mounts' => $this->mounts,
            'volumes' => $this->volumes,
            'Networks' => $this->networks,
            'cap_add' => $this->capAdd,
            'cap_drop' => $this->capDrop,
            'dns_server' => $this->dnsServers,
            'healthconfig' => $this->healthConfig,
            'resource_limits' => $this->resourceLimits,
            ...$this->extra,
        ];
    }
}
