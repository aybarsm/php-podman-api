<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Exec;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * An exec session's state.
 *
 * ExecInspectLibpod documents no response schema; the shape is InspectExecSession, which the spec attaches to the
 * compat ExecInspect operation served by the same handler.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/InspectExecSession
 */
final readonly class ExecInspect implements Hydratable
{
    public function __construct(
        public string $id,
        public ?string $containerId = null,
        public bool $running = false,
        /** Exit code; 0 while the session is still running */
        public ?int $exitCode = null,
        /** PID of the session's process; 0 when not running */
        public ?int $pid = null,
        public bool $openStdin = false,
        public bool $openStdout = false,
        public bool $openStderr = false,
        /** Legacy, for compatibility only */
        public bool $canRemove = false,
        public ?string $detachKeys = null,
        public ?ExecProcessConfig $processConfig = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'ID'),
            containerId: Data::stringOrNull($data, 'ContainerID'),
            running: Data::bool($data, 'Running'),
            exitCode: Data::intOrNull($data, 'ExitCode'),
            pid: Data::intOrNull($data, 'Pid'),
            openStdin: Data::bool($data, 'OpenStdin'),
            openStdout: Data::bool($data, 'OpenStdout'),
            openStderr: Data::bool($data, 'OpenStderr'),
            canRemove: Data::bool($data, 'CanRemove'),
            detachKeys: Data::stringOrNull($data, 'DetachKeys'),
            processConfig: Data::objectOrNull($data, 'ProcessConfig', ExecProcessConfig::fromArray(...)),
        );
    }
}
