<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Images::removeMany() (ImageDeleteAllLibpod).
 *
 * The spec documents `all` with default true, so it is always sent explicitly: removing every image requires
 * `all: true`.
 */
final readonly class ImageRemoveManyOptions implements QueryParameters
{
    /**
     * @param list<string>|null $images image IDs or names to remove
     */
    public function __construct(
        public ?array $images = null,
        /** Remove all images */
        public bool $all = false,
        /** Also remove containers using the images */
        public ?bool $force = null,
        /** Do not fail when a specified image does not exist */
        public ?bool $ignore = null,
        /** Resolve names to manifest lists instead of images */
        public ?bool $lookupManifest = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'images' => $this->images,
            'all' => $this->all,
            'force' => $this->force,
            'ignore' => $this->ignore,
            'lookupManifest' => $this->lookupManifest,
        ];
    }
}
