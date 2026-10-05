<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Secret;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SecretSpec (degraded in v5.8)
 */
final readonly class SecretSpec implements Hydratable
{
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        public ?string $name = null,
        public ?SecretDriver $driver = null,
        public array $labels = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::stringOrNull($data, 'Name'),
            driver: Data::objectOrNull($data, 'Driver', SecretDriver::fromArray(...)),
            labels: Data::stringMap($data, 'Labels'),
        );
    }
}
