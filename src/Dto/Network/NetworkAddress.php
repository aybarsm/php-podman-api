<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * An address assigned to a container interface.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/NetAddress (degraded in v5.8)
 */
final readonly class NetworkAddress implements Hydratable
{
    public function __construct(
        /** Address in CIDR form, e.g. "10.89.0.2/24" */
        public ?string $ipnet = null,
        /** Empty when the network has no gateway (e.g. internal networks) */
        public ?string $gateway = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            ipnet: self::ipnet($data),
            gateway: Data::stringOrNull($data, 'gateway'),
        );
    }

    /**
     * The spec types `ipnet` as Go's net.IPNet object {IP, Mask}, but libnetwork's IPNet marshals as a CIDR string.
     * Accept both; the object form keeps only its IP.
     *
     * @param array<string, mixed> $data
     */
    private static function ipnet(array $data): ?string
    {
        $value = Data::raw($data, 'ipnet');

        return is_array($value)
            ? Data::stringOrNull($value, 'IP')
            : Data::stringOrNull($data, 'ipnet');
    }
}
