<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A network subnet. Used in responses and in NetworkCreateRequest.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/Subnet
 */
final readonly class Subnet implements Hydratable
{
    public function __construct(
        /** CIDR, e.g. "10.89.0.0/24" */
        public string $subnet,
        public ?string $gateway = null,
        public ?LeaseRange $leaseRange = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            subnet: Data::string($data, 'subnet'),
            gateway: Data::stringOrNull($data, 'gateway'),
            leaseRange: Data::objectOrNull($data, 'lease_range', LeaseRange::fromArray(...)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subnet' => $this->subnet,
            'gateway' => $this->gateway,
            'lease_range' => $this->leaseRange?->toArray(),
        ];
    }
}
