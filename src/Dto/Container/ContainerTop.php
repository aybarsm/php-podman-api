<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Processes running in a container (`podman top`).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/ContainerTopOKBody
 */
final readonly class ContainerTop implements Hydratable
{
    /**
     * @param list<string>       $titles    ps column titles
     * @param list<list<string>> $processes one row per process, aligned with $titles
     */
    public function __construct(
        public array $titles = [],
        public array $processes = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            titles: Data::stringList($data, 'Titles'),
            processes: array_map(
                static fn (mixed $row): array => Data::stringList(['row' => $row], 'row'),
                Data::list($data, 'Processes'),
            ),
        );
    }

    /**
     * Rows keyed by column title.
     *
     * @return list<array<string, string>>
     */
    public function rows(): array
    {
        return array_map(
            fn (array $row): array => array_combine(array_slice($this->titles, 0, count($row)), array_slice($row, 0, count($this->titles))),
            $this->processes,
        );
    }
}
