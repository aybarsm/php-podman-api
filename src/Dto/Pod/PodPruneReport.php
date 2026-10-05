<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * @see resources/podman/swagger-v5.7.yaml#/definitions/PodPruneReport (degraded in v5.8)
 */
final readonly class PodPruneReport implements Hydratable
{
    public function __construct(
        public string $id,
        public ?string $error = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            error: Data::errorOrNull($data, 'Err'),
        );
    }
}
