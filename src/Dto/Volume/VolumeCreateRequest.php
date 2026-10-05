<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Volume;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for Volumes::create() (VolumeCreateLibpod).
 *
 * The compatibility-only `Label` field is not modelled: `Labels` is preferred and overrides it.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/VolumeCreateOptions
 */
final readonly class VolumeCreateRequest implements RequestBody
{
    /**
     * @param array<string, string>|null $labels
     * @param array<string, string>|null $options driver options, e.g. ["type" => "tmpfs", "device" => "tmpfs", "o" => "size=100m"]
     */
    public function __construct(
        /** Left blank, Podman generates a name */
        public ?string $name = null,
        /** Volume driver (default "local") */
        public ?string $driver = null,
        public ?array $labels = null,
        public ?array $options = null,
        /** Do not fail when a volume with this name already exists */
        public ?bool $ignoreIfExists = null,
        /** @since Podman 5.6 (spec) */
        public ?int $uid = null,
        /** @since Podman 5.6 (spec) */
        public ?int $gid = null,
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'Name' => $this->name,
            'Driver' => $this->driver,
            'Labels' => $this->labels,
            'Options' => $this->options,
            'IgnoreIfExists' => $this->ignoreIfExists,
            'UID' => $this->uid,
            'GID' => $this->gid,
        ];
    }
}
