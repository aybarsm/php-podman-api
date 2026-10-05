<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * @see resources/podman/swagger-v5.8.yaml#/definitions/NetworkPruneReport
 */
final readonly class NetworkPruneReport implements Hydratable
{
    public function __construct(
        public string $name,
        public ?string $error = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::string($data, 'Name'),
            error: Data::errorOrNull($data, 'Error'),
        );
    }
}
