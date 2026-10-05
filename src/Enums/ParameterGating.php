<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Enums;

/**
 * How the client treats query parameters that the spec introduces later than the configured ApiVersion.
 *
 * The per-parameter versions are derived from the spec files, and older spec files sometimes simply omitted
 * parameters the server already accepted. Strict surfaces likely no-ops early; Off sends everything (Podman
 * ignores unknown query keys).
 */
enum ParameterGating: string
{
    /** Throw UnsupportedApiVersionException before sending (default). */
    case Strict = 'strict';

    /** Send every parameter regardless of the configured version. */
    case Off = 'off';
}
