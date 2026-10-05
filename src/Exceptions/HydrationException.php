<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

/**
 * A response payload did not match the shape described by the spec.
 */
final class HydrationException extends PodmanApiException
{
    public static function unexpectedType(string $key, string $expected, mixed $actual): self
    {
        return new self(sprintf('Expected "%s" to be %s, got %s.', $key, $expected, get_debug_type($actual)));
    }

    public static function missingKey(string $key): self
    {
        return new self(sprintf('Required key "%s" is missing from the response.', $key));
    }

    public static function invalidJson(string $operationId, string $error): self
    {
        return new self(sprintf('Response of %s is not valid JSON: %s', $operationId, $error));
    }
}
