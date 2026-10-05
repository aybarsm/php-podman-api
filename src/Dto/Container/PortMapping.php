<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A host → container port mapping. Used both in list responses and in ContainerCreateSpec.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/PortMapping
 */
final readonly class PortMapping implements Hydratable
{
    public function __construct(
        public int $containerPort,
        /** 0 (or null) lets Podman pick a random host port */
        public ?int $hostPort = null,
        public ?string $hostIp = null,
        /** Comma-separated: tcp, udp, sctp (default tcp) */
        public ?string $protocol = null,
        /** Number of consecutive ports to map, starting at containerPort/hostPort */
        public ?int $range = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            containerPort: Data::int($data, 'container_port'),
            hostPort: Data::intOrNull($data, 'host_port'),
            hostIp: Data::stringOrNull($data, 'host_ip'),
            protocol: Data::stringOrNull($data, 'protocol'),
            range: Data::intOrNull($data, 'range'),
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'container_port' => $this->containerPort,
            'host_port' => $this->hostPort,
            'host_ip' => $this->hostIp,
            'protocol' => $this->protocol,
            'range' => $this->range,
        ];
    }
}
