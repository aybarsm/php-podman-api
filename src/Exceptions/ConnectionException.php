<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * The HTTP client could not complete the request (socket missing, connection refused, timeout, …).
 */
final class ConnectionException extends PodmanApiException
{
    public static function fromClientException(string $operationId, ClientExceptionInterface $e): self
    {
        return new self(sprintf('Podman request %s failed: %s', $operationId, $e->getMessage()), 0, $e);
    }
}
