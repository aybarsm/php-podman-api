<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * One build step from the image config's history (ImageInspect::$history).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/History
 */
final readonly class ImageLayerHistory implements Hydratable
{
    public function __construct(
        public ?DateTimeImmutable $created = null,
        /** Command which created the layer */
        public ?string $createdBy = null,
        public ?string $author = null,
        public ?string $comment = null,
        /** True when the step did not produce a filesystem diff */
        public bool $emptyLayer = false,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            created: Data::dateTimeOrNull($data, 'created'),
            createdBy: Data::stringOrNull($data, 'created_by'),
            author: Data::stringOrNull($data, 'author'),
            comment: Data::stringOrNull($data, 'comment'),
            emptyLayer: Data::bool($data, 'empty_layer'),
        );
    }
}
