<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Enums;

/**
 * Container states accepted by the `status` filter of ContainerListLibpod.
 *
 * Response fields keep the raw string as well: Podman reports further internal states (e.g. "configured",
 * "stopped", "stopping"), so read them through tryFrom().
 */
enum ContainerState: string
{
    case Created = 'created';
    case Restarting = 'restarting';
    case Running = 'running';
    case Removing = 'removing';
    case Paused = 'paused';
    case Exited = 'exited';
    case Dead = 'dead';
}
