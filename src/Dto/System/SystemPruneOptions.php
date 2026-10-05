<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Override;

/**
 * Query parameters for System::prune() (SystemPruneLibpod).
 */
final readonly class SystemPruneOptions implements QueryParameters
{
    public function __construct(
        /** Remove all unused images, not just dangling ones */
        public ?bool $all = null,
        /** Also prune unused volumes */
        public ?bool $volumes = null,
        /** Remove containers in storage not controlled by Podman */
        public ?bool $external = null,
        /** Remove build containers */
        public ?bool $build = null,
        /** e.g. `until`, `label` */
        public ?Filters $filters = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'all' => $this->all,
            'volumes' => $this->volumes,
            'external' => $this->external,
            'build' => $this->build,
            'filters' => $this->filters,
        ];
    }
}
