<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Execution parameters used as the base when running a container from the image.
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/ImageConfig
 */
final readonly class ImageConfig implements Hydratable
{
    /**
     * @param list<string>          $cmd          default arguments to the entrypoint
     * @param list<string>          $entrypoint
     * @param list<string>          $env          "KEY=value" entries
     * @param array<string, mixed>  $exposedPorts set of ports, e.g. {"80/tcp": {}}
     * @param array<string, string> $labels
     * @param array<string, mixed>  $volumes      set of directories, e.g. {"/data": {}}
     */
    public function __construct(
        public array $cmd = [],
        public array $entrypoint = [],
        public array $env = [],
        public array $exposedPorts = [],
        public array $labels = [],
        public ?string $stopSignal = null,
        public ?string $user = null,
        public array $volumes = [],
        public ?string $workingDir = null,
        public bool $argsEscaped = false,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            cmd: Data::stringList($data, 'Cmd'),
            entrypoint: Data::stringList($data, 'Entrypoint'),
            env: Data::stringList($data, 'Env'),
            exposedPorts: Data::map($data, 'ExposedPorts'),
            labels: Data::stringMap($data, 'Labels'),
            stopSignal: Data::stringOrNull($data, 'StopSignal'),
            user: Data::stringOrNull($data, 'User'),
            volumes: Data::map($data, 'Volumes'),
            workingDir: Data::stringOrNull($data, 'WorkingDir'),
            argsEscaped: Data::bool($data, 'ArgsEscaped'),
        );
    }
}
