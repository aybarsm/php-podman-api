<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

/**
 * 404 Not Found: no such container, image, pod, network, volume, secret, …
 */
final class NotFoundException extends RequestException {}
