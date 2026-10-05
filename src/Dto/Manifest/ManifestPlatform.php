<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Manifest;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * The platform a manifest list entry is specialised for.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/Schema2PlatformSpec (degraded in v5.8)
 */
final readonly class ManifestPlatform implements Hydratable
{
    /**
     * @param list<string> $osFeatures
     * @param list<string> $features
     */
    public function __construct(
        public ?string $architecture = null,
        public ?string $os = null,
        public ?string $osVersion = null,
        public array $osFeatures = [],
        public ?string $variant = null,
        public array $features = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            architecture: Data::stringOrNull($data, 'architecture'),
            os: Data::stringOrNull($data, 'os'),
            osVersion: Data::stringOrNull($data, 'os.version'),
            osFeatures: Data::stringList($data, 'os.features'),
            variant: Data::stringOrNull($data, 'variant'),
            features: Data::stringList($data, 'features'),
        );
    }
}
