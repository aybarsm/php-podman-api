<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Enums;

/**
 * Systemd `Restart=` policies accepted by GenerateSystemdLibpod (`restartPolicy`).
 */
enum SystemdRestartPolicy: string
{
    case No = 'no';
    case OnSuccess = 'on-success';
    case OnFailure = 'on-failure';
    case OnAbnormal = 'on-abnormal';
    case OnWatchdog = 'on-watchdog';
    case OnAbort = 'on-abort';
    case Always = 'always';
}
