<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Contracts;

/**
 * A response DTO built from a decoded JSON object.
 */
interface Hydratable
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static;
}
