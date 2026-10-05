<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * One registry search hit.
 *
 * @see resources/podman/swagger-v5.8.yaml#/responses/registrySearchResponse (inline schema)
 */
final readonly class ImageSearchResult implements Hydratable
{
    public function __construct(
        /** Canonical name of the image, e.g. "docker.io/library/alpine" */
        public string $name,
        /** Registry index, e.g. "quay.io" */
        public ?string $index = null,
        public ?string $description = null,
        public ?int $stars = null,
        /** Non-empty when it is an official image; the spec types it as a string */
        public ?string $official = null,
        /** Non-empty when built automatically; the spec types it as a string */
        public ?string $automated = null,
        /** Image tag (only with listTags) */
        public ?string $tag = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::string($data, 'Name'),
            index: Data::stringOrNull($data, 'Index'),
            description: Data::stringOrNull($data, 'Description'),
            stars: Data::intOrNull($data, 'Stars'),
            official: Data::stringOrNull($data, 'Official'),
            automated: Data::stringOrNull($data, 'Automated'),
            tag: Data::stringOrNull($data, 'Tag'),
        );
    }

    public function isOfficial(): bool
    {
        return ($this->official ?? '') !== '';
    }

    public function isAutomated(): bool
    {
        return ($this->automated ?? '') !== '';
    }
}
