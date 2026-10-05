<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Containers::commit() (ImageCommitLibpod), excluding the target container.
 */
final readonly class ContainerCommitOptions implements QueryParameters
{
    /**
     * @param list<string>|null $changes Dockerfile instructions to apply, e.g. ["CMD=/bin/foo"]
     */
    public function __construct(
        /** Repository of the created image */
        public ?string $repo = null,
        public ?string $tag = null,
        public ?string $author = null,
        public ?string $comment = null,
        public ?array $changes = null,
        /** Image format: "oci" (default) or "docker" */
        public ?string $format = null,
        public ?bool $pause = null,
        public ?bool $squash = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'repo' => $this->repo,
            'tag' => $this->tag,
            'author' => $this->author,
            'comment' => $this->comment,
            'changes' => $this->changes,
            'format' => $this->format,
            'pause' => $this->pause,
            'squash' => $this->squash,
        ];
    }
}
