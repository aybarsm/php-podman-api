<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Override;

/**
 * Query parameters for Containers::list() (ContainerListLibpod).
 *
 * Filters: ancestor, annotation, before, exited, expose, health, id, is-task, label, name, network, pod, publish,
 * since, status, volume.
 */
final readonly class ContainerListOptions implements QueryParameters
{
    public function __construct(
        /** Include stopped containers (default: running only) */
        public ?bool $all = null,
        /** Return this many most recently created containers, including non-running ones */
        public ?int $limit = null,
        /** Include namespace information */
        public ?bool $namespace = null,
        /** Include SizeRw / SizeRootFs */
        public ?bool $size = null,
        /** Sync container state with the OCI runtime */
        public ?bool $sync = null,
        public ?Filters $filters = null,
        /**
         * Include containers created by external tools that use container storage.
         *
         * @since Podman 5.8 (spec)
         */
        public ?bool $external = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'all' => $this->all,
            'limit' => $this->limit,
            'namespace' => $this->namespace,
            'size' => $this->size,
            'sync' => $this->sync,
            'filters' => $this->filters,
            'external' => $this->external,
        ];
    }
}
