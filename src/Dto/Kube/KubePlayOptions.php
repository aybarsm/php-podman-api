<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Kube;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Internal\Support\Query;
use JsonException;
use Override;

/**
 * Query parameters for Kube::play() (PlayKubeLibpod).
 */
final readonly class KubePlayOptions implements QueryParameters
{
    /**
     * @param array<string, string>|null $annotations  sent as a JSON object
     * @param list<string>|null          $logOptions   logging driver options
     * @param list<string>|null          $network      network mode, or the networks to join
     * @param list<string>|null          $publishPorts ports or port ranges to publish to the host
     * @param list<string>|null          $staticIps    static IPs for the pods
     * @param list<string>|null          $staticMacs   static MACs for the pods
     */
    public function __construct(
        public ?array $annotations = null,
        /** Logging driver for the containers */
        public ?string $logDriver = null,
        public ?array $logOptions = null,
        public ?array $network = null,
        /** Do not set up /etc/hosts in the containers */
        public ?bool $noHosts = null,
        /** Use annotations not truncated to the Kubernetes maximum of 63 characters */
        public ?bool $noTrunc = null,
        public ?array $publishPorts = null,
        /** Publish every port of the YAML (containerPort, hostPort); otherwise only hostPort ones */
        public ?bool $publishAllPorts = null,
        /** Replace existing pods and containers */
        public ?bool $replace = null,
        /** Start a service container before all pods */
        public ?bool $serviceContainer = null,
        /** Start the pods after creating them (spec default true) */
        public ?bool $start = null,
        public ?array $staticIps = null,
        public ?array $staticMacs = null,
        /** Require HTTPS and verify signatures with registries (spec default true) */
        public ?bool $tlsVerify = null,
        /** User namespace mode for the pods */
        public ?string $userns = null,
        /** Clean up all created objects on SIGTERM or when the pods exit */
        public ?bool $wait = null,
        /** Build images from the contexts of a tar upload */
        public ?bool $build = null,
    ) {}

    /**
     * @throws JsonException
     */
    #[Override]
    public function toQuery(): array
    {
        return [
            'annotations' => Query::json($this->annotations),
            'logDriver' => $this->logDriver,
            'logOptions' => $this->logOptions,
            'network' => $this->network,
            'noHosts' => $this->noHosts,
            'noTrunc' => $this->noTrunc,
            'publishPorts' => $this->publishPorts,
            'publishAllPorts' => $this->publishAllPorts,
            'replace' => $this->replace,
            'serviceContainer' => $this->serviceContainer,
            'start' => $this->start,
            'staticIPs' => $this->staticIps,
            'staticMACs' => $this->staticMacs,
            'tlsVerify' => $this->tlsVerify,
            'userns' => $this->userns,
            'wait' => $this->wait,
            'build' => $this->build,
        ];
    }
}
