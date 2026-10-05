<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

/**
 * 409 Conflict: the object is in use or in an incompatible state.
 */
final class ConflictException extends RequestException {}
