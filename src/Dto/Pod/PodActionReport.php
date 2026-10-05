<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Pod;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Result of a pod state change (start, stop, restart, kill, pause, unpause). These share one wire shape;
 * `RawInput` is only sent by start and stop. Embedded in KubePlayReport::$stopReports.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/PodStopReport (degraded in v5.8; same shape as PodStartReport,
 *      PodRestartReport, PodKillReport, PodPauseReport, PodUnpauseReport)
 */
final readonly class PodActionReport implements Hydratable
{
    /**
     * @param list<string> $errors one per container that failed
     */
    public function __construct(
        public string $id,
        public array $errors = [],
        /** The name or ID the caller passed */
        public ?string $rawInput = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            errors: Data::errorList($data, 'Errs'),
            rawInput: Data::stringOrNull($data, 'RawInput'),
        );
    }
}
