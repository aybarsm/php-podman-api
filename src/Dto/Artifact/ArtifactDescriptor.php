<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Artifact;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * An OCI content descriptor: an artifact file (layer), the manifest config or subject.
 *
 * `data` (embedded content) is not modelled: the spec types it as list<uint8> but Go marshals []byte as a base64
 * string. `platform` is kept raw (architecture, os, os.version, os.features, variant).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/Descriptor (degraded in v5.8)
 */
final readonly class ArtifactDescriptor implements Hydratable
{
    /**
     * @param array<string, string> $annotations
     * @param list<string>          $urls
     * @param array<string, mixed>  $platform
     */
    public function __construct(
        public ?string $mediaType = null,
        public ?string $digest = null,
        public ?int $size = null,
        public ?string $artifactType = null,
        public array $annotations = [],
        public array $urls = [],
        public array $platform = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            mediaType: Data::stringOrNull($data, 'mediaType'),
            digest: Data::stringOrNull($data, 'digest'),
            size: Data::intOrNull($data, 'size'),
            artifactType: Data::stringOrNull($data, 'artifactType'),
            annotations: Data::stringMap($data, 'annotations'),
            urls: Data::stringList($data, 'urls'),
            platform: Data::map($data, 'platform'),
        );
    }

    /**
     * The file name, from the standard `org.opencontainers.image.title` annotation.
     */
    public function title(): ?string
    {
        return $this->annotations['org.opencontainers.image.title'] ?? null;
    }
}
