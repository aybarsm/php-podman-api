<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Quadlet;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Outcome of installing quadlet files.
 *
 * @see resources/podman/swagger-v5.8.yaml#/paths/~1libpod~1quadlets/post (inline 200 schema)
 */
final readonly class QuadletInstallReport implements Hydratable
{
    /**
     * @param array<string, string> $installedQuadlets source path → installed path
     * @param array<string, string> $quadletErrors     source path → error message
     */
    public function __construct(
        public array $installedQuadlets = [],
        public array $quadletErrors = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            installedQuadlets: Data::stringMap($data, 'InstalledQuadlets'),
            quadletErrors: Data::stringMap($data, 'QuadletErrors'),
        );
    }

    public function hasErrors(): bool
    {
        return $this->quadletErrors !== [];
    }
}
