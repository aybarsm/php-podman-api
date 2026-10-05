<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Healthcheck status and recent attempts.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/HealthCheckResults
 */
final readonly class HealthCheckResults implements Hydratable
{
    /**
     * @param list<HealthCheckLog> $log
     */
    public function __construct(
        /** starting, healthy or unhealthy */
        public ?string $status = null,
        public ?int $failingStreak = null,
        public array $log = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            status: Data::stringOrNull($data, 'Status'),
            failingStreak: Data::intOrNull($data, 'FailingStreak'),
            log: Data::objectList($data, 'Log', HealthCheckLog::fromArray(...)),
        );
    }

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }
}
