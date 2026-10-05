<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Override;

/**
 * Query parameters for Images::search() (ImageSearchLibpod), excluding the search term.
 *
 * Filters: is-automated (true/false), is-official (true/false), stars (minimum number of stars).
 */
final readonly class ImageSearchOptions implements QueryParameters
{
    public function __construct(
        /** Maximum number of results (spec default 25) */
        public ?int $limit = null,
        public ?Filters $filters = null,
        /** Require HTTPS and verify signatures when contacting registries (spec default true) */
        public ?bool $tlsVerify = null,
        /** List the available tags in the repository */
        public ?bool $listTags = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'limit' => $this->limit,
            'filters' => $this->filters,
            'tlsVerify' => $this->tlsVerify,
            'listTags' => $this->listTags,
        ];
    }
}
