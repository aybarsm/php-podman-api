<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Internal\Transport;

/**
 * @internal
 */
enum HttpMethod: string
{
    case Delete = 'DELETE';
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
}
