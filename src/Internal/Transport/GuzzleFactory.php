<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Internal\Transport;

use Aybarsm\Podman\Api\ClientConfig;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Builds the default Guzzle client (used when no PSR-18 client is injected).
 *
 * @internal
 */
final class GuzzleFactory
{
    public static function create(ClientConfig $config): Client
    {
        return new Client(self::options($config));
    }

    /**
     * @return array<string, mixed>
     */
    public static function options(ClientConfig $config): array
    {
        $options = [
            RequestOptions::TIMEOUT => $config->timeout,
            RequestOptions::CONNECT_TIMEOUT => $config->connectTimeout,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ALLOW_REDIRECTS => false,
        ];

        if ($config->socketPath !== null) {
            $options['curl'] = [CURLOPT_UNIX_SOCKET_PATH => $config->socketPath];
        }

        return $options;
    }
}
