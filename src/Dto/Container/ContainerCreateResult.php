<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ContainerCreateResponse (degraded in v5.8)
 */
final readonly class ContainerCreateResult implements Hydratable
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public string $id,
        public array $warnings = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            warnings: Data::stringList($data, 'Warnings'),
        );
    }
}
