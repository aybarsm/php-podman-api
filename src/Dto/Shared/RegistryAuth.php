<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Shared;

use JsonException;

/**
 * Credentials for the `X-Registry-Auth` header: either username/password (+ optional email/server) or an identity token.
 */
final readonly class RegistryAuth
{
    public const string HEADER = 'X-Registry-Auth';

    public function __construct(
        public ?string $username = null,
        public ?string $password = null,
        public ?string $email = null,
        public ?string $serverAddress = null,
        public ?string $identityToken = null,
    ) {}

    public static function credentials(string $username, string $password, ?string $serverAddress = null): self
    {
        return new self(username: $username, password: $password, serverAddress: $serverAddress);
    }

    public static function token(string $identityToken, ?string $serverAddress = null): self
    {
        return new self(serverAddress: $serverAddress, identityToken: $identityToken);
    }

    /**
     * Request headers for an optional RegistryAuth.
     *
     * @return array<string, string>
     *
     * @throws JsonException
     */
    public static function headers(?self $auth): array
    {
        return $auth === null ? [] : [self::HEADER => $auth->toHeader()];
    }

    /**
     * base64url (padded) JSON, as decoded by Podman's pkg/auth.
     *
     * @throws JsonException
     */
    public function toHeader(): string
    {
        $payload = array_filter([
            'username' => $this->username,
            'password' => $this->password,
            'email' => $this->email,
            'serveraddress' => $this->serverAddress,
            'identitytoken' => $this->identityToken,
        ], static fn (?string $v): bool => $v !== null);

        return strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), '+/', '-_');
    }
}
