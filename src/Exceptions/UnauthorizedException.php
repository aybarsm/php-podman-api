<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

/**
 * 401 Unauthorized: registry credentials were missing or rejected.
 */
final class UnauthorizedException extends RequestException {}
