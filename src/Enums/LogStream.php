<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Enums;

use Aybarsm\Podman\Api\Exceptions\HydrationException;

/**
 * Stream a multiplexed log/attach frame belongs to. The spec documents frame type 0 = stdin (written on stdout),
 * 1 = stdout, 2 = stderr (ContainerAttachLibpod, "Stream format").
 */
enum LogStream: string
{
    case Stdin = 'stdin';
    case Stdout = 'stdout';
    case Stderr = 'stderr';

    public static function fromFrameType(int $type): self
    {
        return match ($type) {
            0 => self::Stdin,
            1 => self::Stdout,
            2 => self::Stderr,
            default => throw HydrationException::unexpectedType('frame type', '0, 1 or 2', $type),
        };
    }
}
