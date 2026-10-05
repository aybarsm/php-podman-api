<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Pod\PodCreateSpec;
use Aybarsm\Podman\Api\Dto\Pod\PodInspect;
use Aybarsm\Podman\Api\Dto\Pod\PodPruneReport;
use Aybarsm\Podman\Api\Dto\Pod\PodRemoveReport;
use Aybarsm\Podman\Api\Dto\Pod\PodStats;
use Aybarsm\Podman\Api\Dto\Pod\PodSummary;
use Aybarsm\Podman\Api\Dto\Pod\PodTop;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;

/**
 * Libpod `pods` operations.
 *
 * Kube generation and play (GenerateKube, GenerateSystemd, PlayKube, …) live in the Kube resource.
 * State changes answer 409 with a per-container error report when some containers fail; that surfaces as
 * ConflictException.
 */
final readonly class Pods extends AbstractResource
{
    /**
     * List pods (`podman pod ps`).
     *
     * @param Filters|null $filters id, label, name, until, status, network, ctr-names, ctr-ids, ctr-status, ctr-number
     *
     * @return list<PodSummary>
     *
     * @throws BadRequestException on invalid filters
     */
    public function list(?Filters $filters = null): array
    {
        $result = $this->transport->send(Operation::PodList, query: ['filters' => $filters]);

        return Data::listOf($result->jsonList(), PodSummary::fromArray(...));
    }

    /**
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId): PodInspect
    {
        return PodInspect::fromArray($this->transport->send(Operation::PodInspect, ['name' => $nameOrId])->jsonObject());
    }

    public function exists(string $nameOrId): bool
    {
        return $this->probe(Operation::PodExists, ['name' => $nameOrId]);
    }

    /**
     * Create a pod (`podman pod create`); returns its ID.
     *
     * @throws BadRequestException
     * @throws ConflictException when the name is already in use
     */
    public function create(?PodCreateSpec $spec = null): string
    {
        $result = $this->transport->send(Operation::PodCreate, body: $spec ?? []);

        return Data::string($result->jsonObject(), 'Id');
    }

    /**
     * Remove a pod (`podman pod rm`).
     *
     * @param bool     $force   stop and remove running containers first
     * @param int|null $timeout seconds to wait before killing containers (spec 5.8+, ignored by older servers)
     *
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function remove(string $nameOrId, bool $force = false, ?int $timeout = null): PodRemoveReport
    {
        $result = $this->transport->send(
            Operation::PodDelete,
            ['name' => $nameOrId],
            ['force' => $force ?: null, 'timeout' => $timeout],
        );

        return PodRemoveReport::fromArray($result->jsonObject());
    }

    /**
     * @return bool false when the pod was already running (HTTP 304)
     *
     * @throws NotFoundException
     * @throws ConflictException when some containers failed to start
     */
    public function start(string $nameOrId): bool
    {
        return ! $this->transport->send(Operation::PodStart, ['name' => $nameOrId])->isNotModified();
    }

    /**
     * @param int|null $timeout seconds to wait before killing the containers
     *
     * @return bool false when the pod was already stopped (HTTP 304)
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException when some containers failed to stop
     */
    public function stop(string $nameOrId, ?int $timeout = null): bool
    {
        return ! $this->transport
            ->send(Operation::PodStop, ['name' => $nameOrId], ['t' => $timeout])
            ->isNotModified();
    }

    /**
     * @throws NotFoundException
     * @throws ConflictException when some containers failed to restart
     */
    public function restart(string $nameOrId): void
    {
        $this->transport->send(Operation::PodRestart, ['name' => $nameOrId]);
    }

    /**
     * Send a signal to every container of the pod (default SIGKILL).
     *
     * @param string|null $signal name ("SIGTERM") or number ("15")
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException when the pod is not running or some containers could not be signalled
     */
    public function kill(string $nameOrId, ?string $signal = null): void
    {
        $this->transport->send(Operation::PodKill, ['name' => $nameOrId], ['signal' => $signal]);
    }

    /**
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function pause(string $nameOrId): void
    {
        $this->transport->send(Operation::PodPause, ['name' => $nameOrId]);
    }

    /**
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function unpause(string $nameOrId): void
    {
        $this->transport->send(Operation::PodUnpause, ['name' => $nameOrId]);
    }

    /**
     * Processes running in the pod (`podman pod top`). Always a single snapshot (`stream=false`).
     *
     * @param string|null $psArgs ps arguments, e.g. "aux"
     *
     * @throws NotFoundException
     */
    public function top(string $nameOrId, ?string $psArgs = null): PodTop
    {
        $result = $this->transport->send(
            Operation::PodTop,
            ['name' => $nameOrId],
            ['stream' => false, 'ps_args' => $psArgs],
        );

        return PodTop::fromArray($result->jsonObject());
    }

    /**
     * Resource usage of the containers in one or more pods (`podman pod stats --no-stream`). Always a single
     * snapshot (`stream=false`).
     *
     * @param list<string> $namesOrIds pods to report on
     * @param bool         $all        report on all running pods
     *
     * @return list<PodStats> one entry per container
     *
     * @throws NotFoundException
     */
    public function stats(array $namesOrIds = [], bool $all = false): array
    {
        $result = $this->transport->send(Operation::PodStatsAll, query: [
            'all' => $all ?: null,
            'namesOrIDs' => $namesOrIds === [] ? null : $namesOrIds,
            // `stream` (spec 5.8+) defaults to false; omitting it keeps 5.4-5.7 configurations working.
        ]);

        return Data::listOf($result->jsonList(), PodStats::fromArray(...));
    }

    /**
     * Remove all stopped pods (`podman pod prune`).
     *
     * @return list<PodPruneReport>
     *
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function prune(): array
    {
        // The spec documents a single PodPruneReport, but Podman answers with a JSON array of them.
        return Data::listOf($this->transport->send(Operation::PodPrune)->jsonListOrObject(), PodPruneReport::fromArray(...));
    }
}
