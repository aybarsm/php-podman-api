<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Shared;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * One pruned object (container, image or volume) and the space it freed.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/PruneReport
 */
final readonly class PruneReport implements Hydratable
{
    public function __construct(
        public string $id,
        public ?int $size = null,
        public ?string $error = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            size: Data::intOrNull($data, 'Size'),
            error: Data::errorOrNull($data, 'Err'),
        );
    }
}
