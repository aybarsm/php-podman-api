<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Enums;

/**
 * Pod statuses accepted by the `status` filter of PodListLibpod.
 *
 * Response fields keep the raw string: Podman reports them capitalised ("Running") and may add values, so read them
 * through PodSummary::knownStatus() / PodInspect::knownState(), which match case-insensitively.
 */
enum PodStatus: string
{
    case Stopped = 'stopped';
    case Running = 'running';
    case Paused = 'paused';
    case Exited = 'exited';
    case Dead = 'dead';
    case Created = 'created';
    case Degraded = 'degraded';

    /**
     * Case-insensitive lookup for response values; null for unknown or missing values.
     */
    public static function fromResponse(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom(strtolower($value));
    }
}
