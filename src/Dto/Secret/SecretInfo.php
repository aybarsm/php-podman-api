<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Secret;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * A secret's metadata (`podman secret inspect`), also returned by list.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/SecretInfoReport (degraded in v5.8)
 */
final readonly class SecretInfo implements Hydratable
{
    public function __construct(
        public string $id,
        public ?SecretSpec $spec = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        /** The secret value; only present when inspected with showSecret */
        public ?string $secretData = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'ID'),
            spec: Data::objectOrNull($data, 'Spec', SecretSpec::fromArray(...)),
            createdAt: Data::dateTimeOrNull($data, 'CreatedAt'),
            updatedAt: Data::dateTimeOrNull($data, 'UpdatedAt'),
            secretData: Data::stringOrNull($data, 'SecretData'),
        );
    }

    public function name(): ?string
    {
        return $this->spec?->name;
    }
}
