<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Enums\ContainerState;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * Runtime state of an inspected container.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/InspectContainerState
 */
final readonly class ContainerInspectState implements Hydratable
{
    public function __construct(
        /** Raw state; see knownState() */
        public ?string $status = null,
        public bool $running = false,
        public bool $paused = false,
        public bool $restarting = false,
        public bool $oomKilled = false,
        public bool $dead = false,
        public bool $stoppedByUser = false,
        public ?int $pid = null,
        public ?int $conmonPid = null,
        public ?int $exitCode = null,
        public ?string $error = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $finishedAt = null,
        public ?HealthCheckResults $health = null,
        public ?string $ociVersion = null,
        public ?string $cgroupPath = null,
        public bool $checkpointed = false,
        public ?DateTimeImmutable $checkpointedAt = null,
        public ?string $checkpointPath = null,
        public ?string $checkpointLog = null,
        public bool $restored = false,
        public ?DateTimeImmutable $restoredAt = null,
        public ?string $restoreLog = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            status: Data::stringOrNull($data, 'Status'),
            running: Data::bool($data, 'Running'),
            paused: Data::bool($data, 'Paused'),
            restarting: Data::bool($data, 'Restarting'),
            oomKilled: Data::bool($data, 'OOMKilled'),
            dead: Data::bool($data, 'Dead'),
            stoppedByUser: Data::bool($data, 'StoppedByUser'),
            pid: Data::intOrNull($data, 'Pid'),
            conmonPid: Data::intOrNull($data, 'ConmonPid'),
            exitCode: Data::intOrNull($data, 'ExitCode'),
            error: Data::stringOrNull($data, 'Error'),
            startedAt: Data::dateTimeOrNull($data, 'StartedAt'),
            finishedAt: Data::dateTimeOrNull($data, 'FinishedAt'),
            health: Data::objectOrNull($data, 'Health', HealthCheckResults::fromArray(...)),
            ociVersion: Data::stringOrNull($data, 'OciVersion'),
            cgroupPath: Data::stringOrNull($data, 'CgroupPath'),
            checkpointed: Data::bool($data, 'Checkpointed'),
            checkpointedAt: Data::dateTimeOrNull($data, 'CheckpointedAt'),
            checkpointPath: Data::stringOrNull($data, 'CheckpointPath'),
            checkpointLog: Data::stringOrNull($data, 'CheckpointLog'),
            restored: Data::bool($data, 'Restored'),
            restoredAt: Data::dateTimeOrNull($data, 'RestoredAt'),
            restoreLog: Data::stringOrNull($data, 'RestoreLog'),
        );
    }

    public function knownState(): ?ContainerState
    {
        return $this->status === null ? null : ContainerState::tryFrom($this->status);
    }
}
