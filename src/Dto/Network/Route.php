<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A static route of a network. Used in responses and in NetworkCreateRequest.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/Route
 */
final readonly class Route implements Hydratable
{
    public function __construct(
        /** CIDR */
        public string $destination,
        /** Required for unicast routes, empty for blackhole/unreachable/prohibit */
        public ?string $gateway = null,
        public ?int $metric = null,
        /** Spec RouteType (an unenumerated string) */
        public ?string $routeType = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            destination: Data::string($data, 'destination'),
            gateway: Data::stringOrNull($data, 'gateway'),
            metric: Data::intOrNull($data, 'metric'),
            routeType: Data::stringOrNull($data, 'route_type'),
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'destination' => $this->destination,
            'gateway' => $this->gateway,
            'metric' => $this->metric,
            'route_type' => $this->routeType,
        ];
    }
}
