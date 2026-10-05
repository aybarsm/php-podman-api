<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * The range of a subnet in which IPs are leased. Used in responses and in NetworkCreateRequest.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/LeaseRange
 */
final readonly class LeaseRange implements Hydratable
{
    public function __construct(
        /** First IP of the subnet to assign */
        public ?string $startIp = null,
        /** Last IP of the subnet to assign */
        public ?string $endIp = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            startIp: Data::stringOrNull($data, 'start_ip'),
            endIp: Data::stringOrNull($data, 'end_ip'),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'start_ip' => $this->startIp,
            'end_ip' => $this->endIp,
        ];
    }
}
