<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Enums;

/**
 * Podman API versions with a spec in resources/podman/. The value is the URL prefix segment (/v{value}/libpod/…).
 */
enum ApiVersion: string
{
    case V5_4 = '5.4.0';
    case V5_5 = '5.5.0';
    case V5_6 = '5.6.0';
    case V5_7 = '5.7.0';
    case V5_8 = '5.8.0';

    public static function latest(): self
    {
        $cases = self::cases();

        return $cases[array_key_last($cases)];
    }

    public static function minimum(): self
    {
        return self::cases()[0];
    }

    /**
     * Resolves a server-reported version ("5.6.2", "5.6.0-dev") to the newest known API version not above it.
     */
    public static function fromServerVersion(string $version): ?self
    {
        if (preg_match('/^(\d+)\.(\d+)/', $version, $m) !== 1) {
            return null;
        }

        $match = null;
        foreach (self::cases() as $case) {
            if (version_compare($case->value, "{$m[1]}.{$m[2]}.0", '<=')) {
                $match = $case;
            }
        }

        return $match;
    }

    public function isAtLeast(self $other): bool
    {
        return version_compare($this->value, $other->value, '>=');
    }
}
