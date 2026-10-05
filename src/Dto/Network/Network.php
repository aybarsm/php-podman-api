<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * A network as returned by the list and create endpoints.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/Network
 */
final readonly class Network implements Hydratable
{
    /**
     * @param list<Subnet>          $subnets
     * @param list<Route>           $routes
     * @param array<string, string> $labels
     * @param array<string, string> $options           driver options
     * @param array<string, string> $ipamOptions
     * @param list<string>          $networkDnsServers custom DNS servers for the network's resolver
     */
    public function __construct(
        public string $id,
        public string $name,
        /** e.g. bridge, macvlan */
        public ?string $driver = null,
        /** Network interface name on the host */
        public ?string $networkInterface = null,
        public ?DateTimeImmutable $created = null,
        public array $subnets = [],
        public array $routes = [],
        public bool $ipv6Enabled = false,
        public bool $internal = false,
        public bool $dnsEnabled = false,
        public array $labels = [],
        public array $options = [],
        public array $ipamOptions = [],
        public array $networkDnsServers = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'id'),
            name: Data::string($data, 'name'),
            driver: Data::stringOrNull($data, 'driver'),
            networkInterface: Data::stringOrNull($data, 'network_interface'),
            created: Data::dateTimeOrNull($data, 'created'),
            subnets: Data::objectList($data, 'subnets', Subnet::fromArray(...)),
            routes: Data::objectList($data, 'routes', Route::fromArray(...)),
            ipv6Enabled: Data::bool($data, 'ipv6_enabled'),
            internal: Data::bool($data, 'internal'),
            dnsEnabled: Data::bool($data, 'dns_enabled'),
            labels: Data::stringMap($data, 'labels'),
            options: Data::stringMap($data, 'options'),
            ipamOptions: Data::stringMap($data, 'ipam_options'),
            networkDnsServers: Data::stringList($data, 'network_dns_servers'),
        );
    }
}
