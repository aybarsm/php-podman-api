<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Enums;

/**
 * The `operation` of a ManifestModifyLibpod request (ManifestModifyOptions).
 *
 * - Update uses all fields
 * - Remove uses only `operation` and `images`
 * - Annotate uses only `operation` and `annotations`
 */
enum ManifestOperation: string
{
    case Update = 'update';
    case Remove = 'remove';
    case Annotate = 'annotate';
}
