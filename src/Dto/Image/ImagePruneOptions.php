<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Override;

/**
 * Query parameters for Images::prune() (ImagePruneLibpod).
 *
 * Filters: dangling (true/false), until (timestamp or Go duration), label / label! (key or key=value).
 */
final readonly class ImagePruneOptions implements QueryParameters
{
    public function __construct(
        /** Remove all images not in use by containers, not just dangling ones */
        public ?bool $all = null,
        /** Remove images even when they are used by external containers (e.g. build containers) */
        public ?bool $external = null,
        /** Remove the persistent build cache created by `--mount=type=cache` */
        public ?bool $buildCache = null,
        public ?Filters $filters = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'all' => $this->all,
            'external' => $this->external,
            'buildcache' => $this->buildCache,
            'filters' => $this->filters,
        ];
    }
}
