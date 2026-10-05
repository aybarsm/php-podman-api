<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * Low-level information about an image (`podman image inspect`).
 *
 * GraphDriver (DriverData) and RootFS are flattened into graphDriver* / rootFs* properties.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/ImageData
 */
final readonly class ImageInspect implements Hydratable
{
    /**
     * @param list<string>             $repoTags
     * @param list<string>             $repoDigests
     * @param list<string>             $namesHistory    names the image had in the past
     * @param array<string, string>    $labels
     * @param array<string, string>    $annotations
     * @param list<ImageLayerHistory>  $history
     * @param array<string, string>    $graphDriverData low-level storage metadata
     * @param list<string>             $rootFsLayers    layer digests
     * @param array<string, mixed>     $healthcheck     Schema2HealthConfig (Test, Interval, Timeout, StartPeriod, StartInterval, Retries; durations in ns)
     */
    public function __construct(
        public string $id,
        public ?string $digest = null,
        public array $repoTags = [],
        public array $repoDigests = [],
        public array $namesHistory = [],
        public ?string $parent = null,
        public ?string $comment = null,
        public ?DateTimeImmutable $created = null,
        public ?ImageConfig $config = null,
        public ?string $version = null,
        public ?string $author = null,
        public ?string $architecture = null,
        public ?string $os = null,
        public ?int $size = null,
        public ?int $virtualSize = null,
        public ?string $user = null,
        public array $labels = [],
        public array $annotations = [],
        public ?string $manifestType = null,
        public array $history = [],
        public ?string $graphDriverName = null,
        public array $graphDriverData = [],
        public ?string $rootFsType = null,
        public array $rootFsLayers = [],
        public array $healthcheck = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        $graphDriver = Data::map($data, 'GraphDriver');
        $rootFs = Data::map($data, 'RootFS');

        return new self(
            id: Data::string($data, 'Id'),
            digest: Data::stringOrNull($data, 'Digest'),
            repoTags: Data::stringList($data, 'RepoTags'),
            repoDigests: Data::stringList($data, 'RepoDigests'),
            namesHistory: Data::stringList($data, 'NamesHistory'),
            parent: Data::stringOrNull($data, 'Parent'),
            comment: Data::stringOrNull($data, 'Comment'),
            created: Data::dateTimeOrNull($data, 'Created'),
            config: Data::objectOrNull($data, 'Config', ImageConfig::fromArray(...)),
            version: Data::stringOrNull($data, 'Version'),
            author: Data::stringOrNull($data, 'Author'),
            architecture: Data::stringOrNull($data, 'Architecture'),
            os: Data::stringOrNull($data, 'Os'),
            size: Data::intOrNull($data, 'Size'),
            virtualSize: Data::intOrNull($data, 'VirtualSize'),
            user: Data::stringOrNull($data, 'User'),
            labels: Data::stringMap($data, 'Labels'),
            annotations: Data::stringMap($data, 'Annotations'),
            manifestType: Data::stringOrNull($data, 'ManifestType'),
            history: Data::objectList($data, 'History', ImageLayerHistory::fromArray(...)),
            graphDriverName: Data::stringOrNull($graphDriver, 'Name'),
            graphDriverData: Data::stringMap($graphDriver, 'Data'),
            rootFsType: Data::stringOrNull($rootFs, 'Type'),
            rootFsLayers: Data::stringList($rootFs, 'Layers'),
            healthcheck: Data::map($data, 'Healthcheck'),
        );
    }
}
