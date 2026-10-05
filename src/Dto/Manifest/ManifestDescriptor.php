<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Manifest;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * One instance (per-platform manifest) inside a manifest list.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/Schema2ManifestDescriptor (degraded in v5.8)
 */
final readonly class ManifestDescriptor implements Hydratable
{
    /**
     * @param list<string> $urls
     */
    public function __construct(
        public string $digest,
        public ?string $mediaType = null,
        public ?int $size = null,
        public ?ManifestPlatform $platform = null,
        public array $urls = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            digest: Data::string($data, 'digest'),
            mediaType: Data::stringOrNull($data, 'mediaType'),
            size: Data::intOrNull($data, 'size'),
            platform: Data::objectOrNull($data, 'platform', ManifestPlatform::fromArray(...)),
            urls: Data::stringList($data, 'urls'),
        );
    }
}
