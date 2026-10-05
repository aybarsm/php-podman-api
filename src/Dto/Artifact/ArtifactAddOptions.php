<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Artifact;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters shared by Artifacts::add() (ArtifactAddLibpod) and Artifacts::addLocal() (ArtifactLocalLibpod),
 * excluding the artifact name and file name/path.
 */
final readonly class ArtifactAddOptions implements QueryParameters
{
    /**
     * @param list<string>|null $annotations "key=value" entries, e.g. ["test=true"]
     */
    public function __construct(
        /** MIME type of the file */
        public ?string $fileMimeType = null,
        public ?array $annotations = null,
        /** Media type describing the artifact */
        public ?string $artifactMimeType = null,
        /** Append the file to an existing artifact */
        public ?bool $append = null,
        /**
         * Replace an existing artifact with the same name.
         *
         * @since Podman 5.7 (spec)
         */
        public ?bool $replace = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'fileMIMEType' => $this->fileMimeType,
            'annotations' => $this->annotations,
            'artifactMIMEType' => $this->artifactMimeType,
            'append' => $this->append,
            'replace' => $this->replace,
        ];
    }
}
