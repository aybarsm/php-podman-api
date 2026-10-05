<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Exec;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for Exec::create() (ContainerExecLibpod, inline `control` schema).
 *
 * @see resources/podman/swagger-v5.8.yaml#/paths/~1libpod~1containers~1{name}~1exec/post
 */
final readonly class ExecCreateRequest implements RequestBody
{
    /**
     * @param list<string>      $cmd     command and arguments to run
     * @param list<string>|null $env     "KEY=value" entries
     */
    public function __construct(
        public array $cmd,
        public ?bool $attachStdin = null,
        public ?bool $attachStdout = null,
        public ?bool $attachStderr = null,
        /** Allocate a pseudo-TTY */
        public ?bool $tty = null,
        public ?array $env = null,
        /** user, user:group, uid or uid:gid */
        public ?string $user = null,
        public ?string $workingDir = null,
        /** Run with extended privileges */
        public ?bool $privileged = null,
        /** Override the detach key sequence, e.g. "ctrl-p,ctrl-q" */
        public ?string $detachKeys = null,
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'Cmd' => $this->cmd,
            'AttachStdin' => $this->attachStdin,
            'AttachStdout' => $this->attachStdout,
            'AttachStderr' => $this->attachStderr,
            'Tty' => $this->tty,
            'Env' => $this->env,
            'User' => $this->user,
            'WorkingDir' => $this->workingDir,
            'Privileged' => $this->privileged,
            'DetachKeys' => $this->detachKeys,
        ];
    }
}
