<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Manifest;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Result of modifying a manifest list.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/ManifestModifyReport
 */
final readonly class ManifestModifyReport implements Hydratable
{
    /**
     * @param list<string> $images images added to or removed from the list
     * @param list<string> $files  files added to the list (artifact updates only)
     * @param list<string> $errors errors associated with the operation
     */
    public function __construct(
        /** Manifest list ID */
        public string $id,
        public array $images = [],
        public array $files = [],
        public array $errors = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            images: Data::stringList($data, 'images'),
            files: Data::stringList($data, 'files'),
            errors: Data::stringList($data, 'errors'),
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
