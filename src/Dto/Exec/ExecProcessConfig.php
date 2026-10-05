<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Exec;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * The process an exec session runs.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/InspectExecProcess
 */
final readonly class ExecProcessConfig implements Hydratable
{
    /**
     * @param list<string> $arguments
     */
    public function __construct(
        /** The command (first element of the exec command) */
        public ?string $entrypoint = null,
        public array $arguments = [],
        public ?string $user = null,
        public bool $tty = false,
        public bool $privileged = false,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            entrypoint: Data::stringOrNull($data, 'entrypoint'),
            arguments: Data::stringList($data, 'arguments'),
            user: Data::stringOrNull($data, 'user'),
            tty: Data::bool($data, 'tty'),
            privileged: Data::bool($data, 'privileged'),
        );
    }
}
