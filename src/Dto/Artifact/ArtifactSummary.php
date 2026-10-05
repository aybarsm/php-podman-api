<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Artifact;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * An artifact as returned by the list endpoint.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ArtifactListReport (degraded in v5.8)
 */
final readonly class ArtifactSummary implements Hydratable
{
    public function __construct(
        public string $name,
        public ?ArtifactManifest $manifest = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::string($data, 'Name'),
            manifest: Data::objectOrNull($data, 'Manifest', ArtifactManifest::fromArray(...)),
        );
    }
}
