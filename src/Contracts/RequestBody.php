<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Contracts;

/**
 * A request object sent as the JSON body. Null values are omitted by the transport.
 */
interface RequestBody
{
    /**
     * @return array<string, mixed>
     */
    public function toBody(): array;
}
