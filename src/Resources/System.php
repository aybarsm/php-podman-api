<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\System\DiskUsage;
use Aybarsm\Podman\Api\Dto\System\Ping;
use Aybarsm\Podman\Api\Dto\System\StorageCheckOptions;
use Aybarsm\Podman\Api\Dto\System\StorageCheckReport;
use Aybarsm\Podman\Api\Dto\System\SystemInfo;
use Aybarsm\Podman\Api\Dto\System\SystemPruneOptions;
use Aybarsm\Podman\Api\Dto\System\SystemPruneReport;
use Aybarsm\Podman\Api\Dto\System\SystemVersion;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Internal\Operation;

/**
 * Libpod `system` operations. Events (SystemEventsLibpod) are a stream and are deferred.
 */
final readonly class System extends AbstractResource
{
    /**
     * Check the service is reachable and read its protocol versions from the response headers.
     */
    public function ping(): Ping
    {
        return Ping::fromHeaders($this->transport->send(Operation::SystemPing)->headers);
    }

    /**
     * Host, storage, registry and version information (`podman info`).
     */
    public function info(): SystemInfo
    {
        return SystemInfo::fromArray($this->transport->send(Operation::SystemInfo)->jsonObject());
    }

    /**
     * Component version information (`podman version`).
     */
    public function version(): SystemVersion
    {
        return SystemVersion::fromArray($this->transport->send(Operation::SystemVersion)->jsonObject());
    }

    /**
     * Disk usage by images, containers and volumes (`podman system df`).
     */
    public function diskUsage(): DiskUsage
    {
        return DiskUsage::fromArray($this->transport->send(Operation::SystemDataUsage)->jsonObject());
    }

    /**
     * Remove unused pods, containers, images, networks and (optionally) volumes (`podman system prune`).
     *
     * @throws BadRequestException
     */
    public function prune(?SystemPruneOptions $options = null): SystemPruneReport
    {
        $result = $this->transport->send(Operation::SystemPrune, query: $options?->toQuery() ?? []);

        return SystemPruneReport::fromArray($result->jsonObject());
    }

    /**
     * Check storage consistency, optionally repairing it (`podman system check`).
     *
     * @throws BadRequestException
     */
    public function check(?StorageCheckOptions $options = null): StorageCheckReport
    {
        $result = $this->transport->send(Operation::SystemCheck, query: $options?->toQuery() ?? []);

        return StorageCheckReport::fromArray($result->jsonObject());
    }
}
