<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Artifact;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * An artifact's manifest and digest (`podman artifact inspect`).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ArtifactInspectReport (degraded in v5.8)
 */
final readonly class ArtifactInspect implements Hydratable
{
    public function __construct(
        public string $name,
        /** Digest of the artifact manifest */
        public ?string $digest = null,
        public ?ArtifactManifest $manifest = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::string($data, 'Name'),
            digest: Data::stringOrNull($data, 'Digest'),
            manifest: Data::objectOrNull($data, 'Manifest', ArtifactManifest::fromArray(...)),
        );
    }
}
