<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

use RuntimeException;

/**
 * Root of every exception thrown by this package. Catch this to handle any client failure.
 */
abstract class PodmanApiException extends RuntimeException {}
