<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Exec\ExecCreateRequest;
use Aybarsm\Podman\Api\Dto\Exec\ExecInspect;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;

/**
 * Libpod `exec` operations.
 *
 * Deferred (see dev-tools/deferred-operations.php): starting a session (ExecStartLibpod, hijacked stream).
 */
final readonly class Exec extends AbstractResource
{
    /**
     * Create an exec session in a running container; returns the session ID.
     * Sessions are removed automatically 5 minutes after they exit.
     *
     * The spec documents no 201 body; Podman answers with `{"Id": "…"}` (spec definition IDResponse).
     *
     * @throws NotFoundException  when the container does not exist
     * @throws ConflictException  when the container is paused
     */
    public function create(string $containerNameOrId, ExecCreateRequest $request): string
    {
        $result = $this->transport->send(Operation::ContainerExec, ['name' => $containerNameOrId], body: $request);

        return Data::string($result->jsonObject(), 'Id');
    }

    /**
     * @throws NotFoundException
     */
    public function inspect(string $id): ExecInspect
    {
        return ExecInspect::fromArray($this->transport->send(Operation::ExecInspect, ['id' => $id])->jsonObject());
    }

    /**
     * Resize the session's TTY. Only works for sessions created and started with a TTY.
     *
     * @param bool $ignoreNotRunning do not fail when the container is not running (spec 5.8+, ignored by older servers)
     *
     * @throws NotFoundException
     */
    public function resize(string $id, int $height, int $width, bool $ignoreNotRunning = false): void
    {
        $this->transport->send(
            Operation::ExecResize,
            ['id' => $id],
            ['h' => $height, 'w' => $width, 'running' => $ignoreNotRunning ?: null],
        );
    }
}
