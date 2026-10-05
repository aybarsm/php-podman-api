<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Contracts;

use Aybarsm\Podman\Api\Dto\Shared\Filters;

/**
 * A request option object that contributes URL query parameters. Null values are omitted.
 */
interface QueryParameters
{
    /**
     * @return array<string, scalar|list<scalar>|Filters|null>
     */
    public function toQuery(): array;
}
