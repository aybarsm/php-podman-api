<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

/**
 * A status code the spec does not map to a more specific exception.
 */
final class UnexpectedStatusException extends RequestException {}
