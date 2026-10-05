<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Kube;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A volume removed by `podman kube down --force`.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/VolumeRmReport (degraded in v5.8)
 */
final readonly class KubeVolumeRemoveReport implements Hydratable
{
    public function __construct(
        /** Volume name */
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
