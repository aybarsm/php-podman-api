<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * One container removed by ContainerDeleteLibpod (several when `depend=true`).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/LibpodContainersRmReport
 */
final readonly class ContainerRemoveReport implements Hydratable
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
