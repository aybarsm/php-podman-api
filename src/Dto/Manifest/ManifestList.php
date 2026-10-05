<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Manifest;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * A manifest list / image index (`podman manifest inspect`).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/Schema2ListPublic (v5.8 Schema2List is degraded)
 */
final readonly class ManifestList implements Hydratable
{
    /**
     * @param list<ManifestDescriptor> $manifests
     */
    public function __construct(
        public ?int $schemaVersion = null,
        public ?string $mediaType = null,
        public array $manifests = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            schemaVersion: Data::intOrNull($data, 'schemaVersion'),
            mediaType: Data::stringOrNull($data, 'mediaType'),
            manifests: Data::objectList($data, 'manifests', ManifestDescriptor::fromArray(...)),
        );
    }
}
