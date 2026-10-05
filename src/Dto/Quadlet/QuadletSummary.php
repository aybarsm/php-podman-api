<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Quadlet;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A quadlet as returned by the list endpoint (`podman quadlet list`).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/ListQuadlet
 */
final readonly class QuadletSummary implements Hydratable
{
    public function __construct(
        /** Quadlet file name, e.g. "myapp.container" */
        public string $name,
        /** Systemd unit generated from the quadlet; empty until systemd is reloaded */
        public ?string $unitName = null,
        /** Path of the quadlet file on disk */
        public ?string $path = null,
        /** Systemd status when loaded, otherwise a note about syntax errors */
        public ?string $status = null,
        /** Application the quadlet was installed with, when several were installed together */
        public ?string $app = null,
        /**
         * Pod quadlet referenced by Pod= (container quadlets only).
         *
         * @since Podman 5.8 (spec)
         */
        public ?string $pod = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::string($data, 'Name'),
            unitName: Data::stringOrNull($data, 'UnitName'),
            path: Data::stringOrNull($data, 'Path'),
            status: Data::stringOrNull($data, 'Status'),
            app: Data::stringOrNull($data, 'App'),
            pod: Data::stringOrNull($data, 'Pod'),
        );
    }
}
