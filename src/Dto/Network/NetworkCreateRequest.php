<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for Networks::create() (NetworkCreateLibpod).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/networkCreateLibpod
 */
final readonly class NetworkCreateRequest implements RequestBody
{
    /**
     * @param list<Subnet>|null          $subnets           omit to let Podman pick a free subnet
     * @param list<Route>|null           $routes
     * @param array<string, string>|null $labels
     * @param array<string, string>|null $options           driver options, e.g. ["mtu" => "1500"]
     * @param array<string, string>|null $ipamOptions       e.g. ["driver" => "dhcp"]
     * @param list<string>|null          $networkDnsServers custom DNS servers for the network's resolver
     * @param array<string, mixed>       $extra             any other networkCreateLibpod field, merged last
     */
    public function __construct(
        public ?string $name = null,
        /** e.g. bridge, macvlan */
        public ?string $driver = null,
        /** Network interface name on the host */
        public ?string $networkInterface = null,
        public ?array $subnets = null,
        public ?array $routes = null,
        public ?bool $ipv6Enabled = null,
        /** No external routes to public or other networks */
        public ?bool $internal = null,
        /** Name resolution for containers on this network (bridge driver only) */
        public ?bool $dnsEnabled = null,
        public ?array $labels = null,
        public ?array $options = null,
        public ?array $ipamOptions = null,
        public ?array $networkDnsServers = null,
        public array $extra = [],
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'name' => $this->name,
            'driver' => $this->driver,
            'network_interface' => $this->networkInterface,
            'subnets' => $this->subnets === null
                ? null
                : array_map(static fn (Subnet $s): array => $s->toArray(), $this->subnets),
            'routes' => $this->routes === null
                ? null
                : array_map(static fn (Route $r): array => $r->toArray(), $this->routes),
            'ipv6_enabled' => $this->ipv6Enabled,
            'internal' => $this->internal,
            'dns_enabled' => $this->dnsEnabled,
            'labels' => $this->labels,
            'options' => $this->options,
            'ipam_options' => $this->ipamOptions,
            'network_dns_servers' => $this->networkDnsServers,
            ...$this->extra,
        ];
    }
}
