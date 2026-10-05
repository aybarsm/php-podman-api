<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Transport\Transport;

/**
 * Base for resource groups. Obtain resources from PodmanClient, never construct them directly.
 */
abstract readonly class AbstractResource
{
    /**
     * @internal
     */
    public function __construct(protected Transport $transport) {}

    /**
     * For `…/exists` operations: 204 → true, 404 → false.
     *
     * @param array<string, string> $path
     */
    protected function probe(Operation $operation, array $path): bool
    {
        try {
            $this->transport->send($operation, $path);

            return true;
        } catch (NotFoundException) {
            return false;
        }
    }
}
