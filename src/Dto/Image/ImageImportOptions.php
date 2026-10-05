<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Images::import() (ImageImportLibpod).
 */
final readonly class ImageImportOptions implements QueryParameters
{
    /**
     * @param list<string>|null $changes Dockerfile instructions to apply: CMD, ENTRYPOINT, ENV, EXPOSE, LABEL, STOPSIGNAL,
     *                                   USER, VOLUME, WORKDIR (e.g. ["CMD=/bin/sh"])
     */
    public function __construct(
        /** Name[:TAG] for the image */
        public ?string $reference = null,
        /** Commit message for the imported image */
        public ?string $message = null,
        public ?array $changes = null,
        /** Load the tarball from this URL instead of the request body */
        public ?string $url = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'changes' => $this->changes,
            'message' => $this->message,
            'reference' => $this->reference,
            'url' => $this->url,
        ];
    }
}
