<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SystemDfImageReport (absent in v5.8)
 */
final readonly class DiskUsageImage implements Hydratable
{
    public function __construct(
        public string $imageId,
        public ?string $repository = null,
        public ?string $tag = null,
        public ?DateTimeImmutable $created = null,
        public ?int $size = null,
        public ?int $sharedSize = null,
        public ?int $uniqueSize = null,
        public ?int $containers = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            imageId: Data::string($data, 'ImageID'),
            repository: Data::stringOrNull($data, 'Repository'),
            tag: Data::stringOrNull($data, 'Tag'),
            created: Data::dateTimeOrNull($data, 'Created'),
            size: Data::intOrNull($data, 'Size'),
            sharedSize: Data::intOrNull($data, 'SharedSize'),
            uniqueSize: Data::intOrNull($data, 'UniqueSize'),
            containers: Data::intOrNull($data, 'Containers'),
        );
    }
}
