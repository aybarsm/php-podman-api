<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

/**
 * 403 Forbidden: e.g. writing into a read-only container filesystem.
 */
final class ForbiddenException extends RequestException {}
