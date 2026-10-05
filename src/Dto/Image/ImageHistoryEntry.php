<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * One parent layer reported by `podman image history`.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/HistoryResponse
 */
final readonly class ImageHistoryEntry implements Hydratable
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        /** Layer/image ID, "<missing>" for intermediate layers that are not images */
        public string $id,
        public ?DateTimeImmutable $created = null,
        public ?string $createdBy = null,
        public array $tags = [],
        public ?int $size = null,
        public ?string $comment = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            created: Data::dateTimeOrNull($data, 'Created'),
            createdBy: Data::stringOrNull($data, 'CreatedBy'),
            tags: Data::stringList($data, 'Tags'),
            size: Data::intOrNull($data, 'Size'),
            comment: Data::stringOrNull($data, 'Comment'),
        );
    }
}
