<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Volume;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * A volume's configuration and state (`podman volume inspect`), also returned by list and create.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/VolumeConfigResponse (degraded in v5.8)
 */
final readonly class VolumeInspect implements Hydratable
{
    /**
     * @param array<string, string> $labels
     * @param array<string, string> $options driver options used when creating the volume
     * @param array<string, mixed>  $status  driver-specific state; only populated by some volume plugins
     */
    public function __construct(
        public string $name,
        public ?string $driver = null,
        /** Path on the host where the volume is mounted */
        public ?string $mountpoint = null,
        public ?DateTimeImmutable $createdAt = null,
        public array $labels = [],
        public array $options = [],
        /** Unused, Docker compatibility only ("local") */
        public ?string $scope = null,
        public array $status = [],
        /** Whether the volume was created as an anonymous volume for a single container */
        public bool $anonymous = false,
        public ?int $uid = null,
        public ?int $gid = null,
        /** Number of times the volume is currently mounted */
        public ?int $mountCount = null,
        public bool $needsChown = false,
        public bool $needsCopyUp = false,
        /** ID of the container backing the volume in c/storage (image volumes only) */
        public ?string $storageId = null,
        /** Driver timeout in seconds, if given */
        public ?int $timeout = null,
        public ?int $lockNumber = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            name: Data::string($data, 'Name'),
            driver: Data::stringOrNull($data, 'Driver'),
            mountpoint: Data::stringOrNull($data, 'Mountpoint'),
            createdAt: Data::dateTimeOrNull($data, 'CreatedAt'),
            labels: Data::stringMap($data, 'Labels'),
            options: Data::stringMap($data, 'Options'),
            scope: Data::stringOrNull($data, 'Scope'),
            status: Data::map($data, 'Status'),
            anonymous: Data::bool($data, 'Anonymous'),
            uid: Data::intOrNull($data, 'UID'),
            gid: Data::intOrNull($data, 'GID'),
            mountCount: Data::intOrNull($data, 'MountCount'),
            needsChown: Data::bool($data, 'NeedsChown'),
            needsCopyUp: Data::bool($data, 'NeedsCopyUp'),
            storageId: Data::stringOrNull($data, 'StorageID'),
            timeout: Data::intOrNull($data, 'Timeout'),
            lockNumber: Data::intOrNull($data, 'LockNumber'),
        );
    }
}
