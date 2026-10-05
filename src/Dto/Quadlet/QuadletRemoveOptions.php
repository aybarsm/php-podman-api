<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Quadlet;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters shared by Quadlets::remove() (QuadletDeleteLibpod) and Quadlets::removeMany()
 * (QuadletDeleteAllLibpod).
 */
final readonly class QuadletRemoveOptions implements QueryParameters
{
    public function __construct(
        /** Stop running quadlets before removing them */
        public ?bool $force = null,
        /** Do not fail for quadlets that do not exist */
        public ?bool $ignore = null,
        /** Reload systemd afterwards (spec default true) */
        public ?bool $reloadSystemd = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'force' => $this->force,
            'ignore' => $this->ignore,
            'reload-systemd' => $this->reloadSystemd,
        ];
    }
}
