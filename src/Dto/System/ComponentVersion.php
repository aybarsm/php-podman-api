<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Version information for a single server component.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ComponentVersion (degraded in v5.8)
 */
final readonly class ComponentVersion implements Hydratable
{
    /**
     * @param array<string, string> $details
     */
    public function __construct(
        public string $name,
        public ?string $version = null,
        public array $details = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::string($data, 'Name'),
            version: Data::stringOrNull($data, 'Version'),
            details: Data::stringMap($data, 'Details'),
        );
    }
}
