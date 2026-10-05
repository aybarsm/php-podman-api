<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Secret;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SecretDriverSpec (degraded in v5.8)
 */
final readonly class SecretDriver implements Hydratable
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(
        /** file, pass or shell */
        public ?string $name = null,
        public array $options = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::stringOrNull($data, 'Name'),
            options: Data::stringMap($data, 'Options'),
        );
    }
}
