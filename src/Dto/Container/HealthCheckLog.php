<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * One healthcheck attempt.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/HealthCheckLog
 */
final readonly class HealthCheckLog implements Hydratable
{
    public function __construct(
        /** Start time as reported by Podman (string per spec) */
        public ?string $start = null,
        public ?string $end = null,
        public ?int $exitCode = null,
        public ?string $output = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            start: Data::stringOrNull($data, 'Start'),
            end: Data::stringOrNull($data, 'End'),
            exitCode: Data::intOrNull($data, 'ExitCode'),
            output: Data::stringOrNull($data, 'Output'),
        );
    }
}
