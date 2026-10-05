<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Result of removing images or manifest lists (ImageDeleteLibpod, ImageDeleteAllLibpod, ManifestDeleteLibpod).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/LibpodImagesRemoveReport (degraded in v5.8)
 */
final readonly class ImageRemoveReport implements Hydratable
{
    /**
     * @param list<string> $deleted  deleted image IDs
     * @param list<string> $untagged untagged references; can be longer than $deleted
     * @param list<string> $errors   errors for individual images
     */
    public function __construct(
        public array $deleted = [],
        public array $untagged = [],
        public array $errors = [],
        /** Exit code as described in the `podman rmi` man page */
        public ?int $exitCode = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            deleted: Data::stringList($data, 'Deleted'),
            untagged: Data::stringList($data, 'Untagged'),
            errors: Data::stringList($data, 'Errors'),
            exitCode: Data::intOrNull($data, 'ExitCode'),
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
