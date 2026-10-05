<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Artifact;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * The OCI image manifest describing an artifact; each layer is one file.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/OCI1 (degraded in v5.8)
 */
final readonly class ArtifactManifest implements Hydratable
{
    /**
     * @param list<ArtifactDescriptor> $layers
     * @param array<string, string>    $annotations
     */
    public function __construct(
        public ?int $schemaVersion = null,
        public ?string $mediaType = null,
        /** IANA media type of the artifact */
        public ?string $artifactType = null,
        public ?ArtifactDescriptor $config = null,
        public array $layers = [],
        public ?ArtifactDescriptor $subject = null,
        public array $annotations = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            schemaVersion: Data::intOrNull($data, 'schemaVersion'),
            mediaType: Data::stringOrNull($data, 'mediaType'),
            artifactType: Data::stringOrNull($data, 'artifactType'),
            config: Data::objectOrNull($data, 'config', ArtifactDescriptor::fromArray(...)),
            layers: Data::objectList($data, 'layers', ArtifactDescriptor::fromArray(...)),
            subject: Data::objectOrNull($data, 'subject', ArtifactDescriptor::fromArray(...)),
            annotations: Data::stringMap($data, 'annotations'),
        );
    }
}
