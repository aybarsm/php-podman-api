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
        /** Set when a query parameter, not the operation itself, is the newer part */
        public readonly ?string $parameter = null,
    ) {
        parent::__construct($parameter === null
            ? sprintf(
                '%s requires Podman API %s or newer; the client is configured for %s.',
                $operationId,
                $required->value,
                $configured->value,
            )
            : sprintf(
                '%s parameter "%s" requires Podman API %s or newer per the spec; the client is configured for %s. '
                .'If your server accepts it anyway, configure ClientConfig with parameterGating: ParameterGating::Off.',
                $operationId,
                $parameter,
                $required->value,
                $configured->value,
            ));
    }
}
