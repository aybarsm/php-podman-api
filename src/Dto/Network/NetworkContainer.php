<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A container attached to a network, as embedded in the inspect response.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/NetworkContainerInfo (degraded in v5.8)
 */
final readonly class NetworkContainer implements Hydratable
{
    /**
     * @param array<string, NetworkInterface> $interfaces interface name (e.g. "eth0") → interface
     */
    public function __construct(
        public ?string $name = null,
        public array $interfaces = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::stringOrNull($data, 'name'),
            interfaces: Data::objectMap($data, 'interfaces', NetworkInterface::fromArray(...)),
        );
    }
}
