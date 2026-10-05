<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

use Aybarsm\Podman\Api\Enums\ApiVersion;

/**
 * The operation was introduced in a newer Podman API version than the client is configured for.
 */
final class UnsupportedApiVersionException extends PodmanApiException
{
    public function __construct(
        public readonly string $operationId,
        public readonly ApiVersion $required,
        public readonly ApiVersion $configured,
    ) {
        parent::__construct(sprintf(
            '%s requires Podman API %s or newer; the client is configured for %s.',
            $operationId,
            $required->value,
            $configured->value,
        ));
    }
}
