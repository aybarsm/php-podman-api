<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Containers::remove() (ContainerDeleteLibpod).
 */
final readonly class ContainerRemoveOptions implements QueryParameters
{
    public function __construct(
        /** Stop the container first if it is running */
        public ?bool $force = null,
        /** Also remove containers that depend on this one */
        public ?bool $depend = null,
        /** Do not fail when the container does not exist */
        public ?bool $ignore = null,
        /** Seconds to wait before killing when force-removing (spec default 10) */
        public ?int $timeout = null,
        /** Also remove anonymous volumes */
        public ?bool $volumes = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'force' => $this->force,
            'depend' => $this->depend,
            'ignore' => $this->ignore,
            'timeout' => $this->timeout,
            'v' => $this->volumes,
        ];
    }
}
