<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Quadlet;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Outcome of removing one or more quadlets.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/QuadletRemoveReport
 */
final readonly class QuadletRemoveReport implements Hydratable
{
    /**
     * @param list<string>          $removed quadlets that were removed
     * @param array<string, string> $errors  quadlet name → error message
     */
    public function __construct(
        public array $removed = [],
        public array $errors = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            removed: Data::stringList($data, 'Removed'),
            errors: Data::stringMap($data, 'Errors'),
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
