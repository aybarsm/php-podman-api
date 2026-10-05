<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api;

use Aybarsm\Podman\Api\Enums\ApiVersion;
use InvalidArgumentException;

/**
 * Connection settings for PodmanClient.
 */
final readonly class ClientConfig
{
    /** Rootful default; rootless sockets live at /run/user/{uid}/podman/podman.sock. */
    public const string DEFAULT_SOCKET = '/run/podman/podman.sock';

    /** Host part is ignored when talking over a unix socket, but PSR-7 needs a valid authority. */
    public const string SOCKET_BASE_URI = 'http://d';

    public const string USER_AGENT = 'aybarsm/podman-api';

    public ApiVersion $apiVersion;

    public string $baseUri;

    /**
     * @param string|null           $socketPath     unix socket path; when set, $baseUri only supplies the Host header
     * @param ApiVersion|null       $apiVersion     server API version to target (default: newest known)
     * @param float                 $timeout        total request timeout in seconds, 0 = none
     * @param float                 $connectTimeout connection timeout in seconds, 0 = none
     * @param array<string, string> $headers        extra headers sent with every request
     */
    public function __construct(
        string $baseUri = self::SOCKET_BASE_URI,
        public ?string $socketPath = null,
        ?ApiVersion $apiVersion = null,
        public float $timeout = 0.0,
        public float $connectTimeout = 5.0,
        public array $headers = [],
        public string $userAgent = self::USER_AGENT,
    ) {
        $scheme = parse_url($baseUri, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Base URI must be an http(s) URL, got \"{$baseUri}\".");
        }
        if ($socketPath === '') {
            throw new InvalidArgumentException('Socket path must not be empty.');
        }

        $this->baseUri = rtrim($baseUri, '/');
        $this->apiVersion = $apiVersion ?? ApiVersion::latest();
    }

    public static function unixSocket(string $socketPath = self::DEFAULT_SOCKET, ?ApiVersion $apiVersion = null): self
    {
        return new self(socketPath: $socketPath, apiVersion: $apiVersion);
    }

    public static function tcp(string $baseUri, ?ApiVersion $apiVersion = null): self
    {
        return new self(baseUri: $baseUri, apiVersion: $apiVersion);
    }

    public function withApiVersion(ApiVersion $apiVersion): self
    {
        return new self($this->baseUri, $this->socketPath, $apiVersion, $this->timeout, $this->connectTimeout, $this->headers, $this->userAgent);
    }

    public function usesUnixSocket(): bool
    {
        return $this->socketPath !== null;
    }
}
