<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * An image as returned by the list endpoint.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/LibpodImageSummary (v5.8 ImageSummary is degraded)
 */
final readonly class ImageSummary implements Hydratable
{
    /**
     * @param list<string>          $names
     * @param list<string>          $repoTags
     * @param list<string>          $repoDigests
     * @param list<string>          $history     IDs of the image's history (parent) layers
     * @param array<string, string> $labels
     */
    public function __construct(
        public string $id,
        public ?string $parentId = null,
        public array $names = [],
        public array $repoTags = [],
        public array $repoDigests = [],
        public ?DateTimeImmutable $created = null,
        public ?int $size = null,
        public ?int $sharedSize = null,
        public ?int $virtualSize = null,
        public array $labels = [],
        /** Number of containers using this image */
        public ?int $containers = null,
        public ?string $digest = null,
        public array $history = [],
        public ?string $arch = null,
        public ?string $os = null,
        public bool $dangling = false,
        public bool $readOnly = false,
        public bool $isManifestList = false,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            parentId: Data::stringOrNull($data, 'ParentId'),
            names: Data::stringList($data, 'Names'),
            repoTags: Data::stringList($data, 'RepoTags'),
            repoDigests: Data::stringList($data, 'RepoDigests'),
            created: Data::dateTimeOrNull($data, 'Created'),
            size: Data::intOrNull($data, 'Size'),
            sharedSize: Data::intOrNull($data, 'SharedSize'),
            virtualSize: Data::intOrNull($data, 'VirtualSize'),
            labels: Data::stringMap($data, 'Labels'),
            containers: Data::intOrNull($data, 'Containers'),
            digest: Data::stringOrNull($data, 'Digest'),
            history: Data::stringList($data, 'History'),
            arch: Data::stringOrNull($data, 'Arch'),
            os: Data::stringOrNull($data, 'Os'),
            dangling: Data::bool($data, 'Dangling'),
            readOnly: Data::bool($data, 'ReadOnly'),
            isManifestList: Data::bool($data, 'IsManifestList'),
        );
    }
}
