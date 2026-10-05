<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * One network removed by NetworkDeleteLibpod.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/NetworkRmReport (degraded in v5.8)
 */
final readonly class NetworkRemoveReport implements Hydratable
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
            error: Data::errorOrNull($data, 'Err'),
        );
    }
}
