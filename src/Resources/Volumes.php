<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Shared\PruneReport;
use Aybarsm\Podman\Api\Dto\Volume\VolumeCreateRequest;
use Aybarsm\Podman\Api\Dto\Volume\VolumeInspect;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Psr\Http\Message\StreamInterface;

/**
 * Libpod `volumes` operations.
 */
final readonly class Volumes extends AbstractResource
{
    /**
     * List volumes (`podman volume ls`).
     *
     * @param Filters|null $filters driver, label, name, opt, until
     *
     * @return list<VolumeInspect>
     */
    public function list(?Filters $filters = null): array
    {
        $result = $this->transport->send(Operation::VolumeList, query: ['filters' => $filters]);

        return Data::listOf($result->jsonList(), VolumeInspect::fromArray(...));
    }

    /**
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId): VolumeInspect
    {
        $result = $this->transport->send(Operation::VolumeInspect, ['name' => $nameOrId]);

        return VolumeInspect::fromArray($result->jsonObject());
    }

    public function exists(string $name): bool
    {
        return $this->probe(Operation::VolumeExists, ['name' => $name]);
    }

    /**
     * Create a volume (`podman volume create`). Without a request, Podman creates an anonymous-named local volume.
     */
    public function create(?VolumeCreateRequest $request = null): VolumeInspect
    {
        $result = $this->transport->send(Operation::VolumeCreate, body: $request ?? []);

        return VolumeInspect::fromArray($result->jsonObject());
    }

    /**
     * Remove a volume (`podman volume rm`).
     *
     * @param bool     $force   also remove containers using the volume
     * @param int|null $timeout seconds before forcibly killing containers using the volume (spec 5.8+, ignored by older servers)
     *
     * @throws NotFoundException
     * @throws ConflictException when the volume is in use and not forced
     */
    public function remove(string $nameOrId, bool $force = false, ?int $timeout = null): void
    {
        $this->transport->send(Operation::VolumeDelete, ['name' => $nameOrId], ['force' => $force ?: null, 'timeout' => $timeout]);
    }

    /**
     * Remove unused volumes (`podman volume prune`).
     *
     * @param Filters|null $filters all, anonymous, until, label
     * @param bool         $dryRun  report what would be pruned without removing it (spec 5.8+, ignored by older servers)
     *
     * @return list<PruneReport>
     */
    public function prune(?Filters $filters = null, bool $dryRun = false): array
    {
        $result = $this->transport->send(Operation::VolumePrune, query: ['filters' => $filters, 'dryrun' => $dryRun ?: null]);

        return Data::listOf($result->jsonList(), PruneReport::fromArray(...));
    }

    /**
     * Export the volume contents as a tar archive (`podman volume export`).
     *
     * @since Podman 5.6
     *
     * @throws NotFoundException
     */
    public function export(string $nameOrId): StreamInterface
    {
        return $this->transport->send(Operation::VolumeExport, ['name' => $nameOrId])->stream();
    }

    /**
     * Populate the volume from an uncompressed tar archive (`podman volume import`).
     *
     * @since Podman 5.6
     *
     * @throws NotFoundException
     */
    public function import(string $nameOrId, StreamInterface|string $tar): void
    {
        $this->transport->send(Operation::VolumeImport, ['name' => $nameOrId], body: $tar, contentType: 'application/x-tar');
    }
}
