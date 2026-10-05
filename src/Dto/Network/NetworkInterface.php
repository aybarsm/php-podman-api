<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A container's network interface.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/NetInterface (degraded in v5.8)
 */
final readonly class NetworkInterface implements Hydratable
{
    /**
     * @param list<NetworkAddress> $subnets assigned addresses with their gateways
     */
    public function __construct(
        public ?string $macAddress = null,
        public array $subnets = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            macAddress: Data::stringOrNull($data, 'mac_address'),
            subnets: Data::objectList($data, 'subnets', NetworkAddress::fromArray(...)),
        );
    }
}
