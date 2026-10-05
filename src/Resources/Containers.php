<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Container\ContainerCommitOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerCreateResult;
use Aybarsm\Podman\Api\Dto\Container\ContainerCreateSpec;
use Aybarsm\Podman\Api\Dto\Container\ContainerInspect;
use Aybarsm\Podman\Api\Dto\Container\ContainerListOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerLogsOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerRemoveOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerRemoveReport;
use Aybarsm\Podman\Api\Dto\Container\ContainerStats;
use Aybarsm\Podman\Api\Dto\Container\ContainerSummary;
use Aybarsm\Podman\Api\Dto\Container\ContainerTop;
use Aybarsm\Podman\Api\Dto\Container\ContainerUpdateRequest;
use Aybarsm\Podman\Api\Dto\Container\HealthCheckResults;
use Aybarsm\Podman\Api\Dto\Container\LogLine;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Shared\PruneReport;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\ForbiddenException;
use Aybarsm\Podman\Api\Exceptions\HydrationException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\ServerException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Aybarsm\Podman\Api\Internal\Support\MultiplexedStream;
use Aybarsm\Podman\Api\Internal\Support\Query;
use JsonException;
use Psr\Http\Message\StreamInterface;

/**
 * Libpod `containers` operations.
 *
 * Deferred (see dev-tools/deferred-operations.php): attach, single-container stats, changes, checkpoint, restore.
 * logs() and statsAll() are bounded (no follow / no streaming).
 * Kube/systemd generation and play live in the Kube resource.
 */
final readonly class Containers extends AbstractResource
{
    /**
     * List containers (`podman ps`). Running only unless `all` is set.
     *
     * @return list<ContainerSummary>
     *
     * @throws BadRequestException on invalid filters
     */
    public function list(?ContainerListOptions $options = null): array
    {
        $result = $this->transport->send(Operation::ContainerList, query: $options?->toQuery() ?? []);

        return Data::listOf($result->jsonList(), ContainerSummary::fromArray(...));
    }

    /**
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId, bool $size = false): ContainerInspect
    {
        $result = $this->transport->send(Operation::ContainerInspect, ['name' => $nameOrId], ['size' => $size ?: null]);

        return ContainerInspect::fromArray($result->jsonObject());
    }

    public function exists(string $nameOrId): bool
    {
        return $this->probe(Operation::ContainerExists, ['name' => $nameOrId]);
    }

    /**
     * Create a container (`podman create`). Does not start it.
     *
     * @throws BadRequestException
     * @throws NotFoundException  when the image is not present locally
     * @throws ConflictException  when the name is already in use
     */
    public function create(ContainerCreateSpec $spec): ContainerCreateResult
    {
        return ContainerCreateResult::fromArray($this->transport->send(Operation::ContainerCreate, body: $spec)->jsonObject());
    }

    /**
     * Remove a container (`podman rm`).
     *
     * @return list<ContainerRemoveReport> one per removed container; empty when Podman answers 204
     *
     * @throws NotFoundException
     * @throws ConflictException when running and not forced
     */
    public function remove(string $nameOrId, ?ContainerRemoveOptions $options = null): array
    {
        $result = $this->transport->send(Operation::ContainerDelete, ['name' => $nameOrId], $options?->toQuery() ?? []);

        return Data::listOf($result->jsonList(), ContainerRemoveReport::fromArray(...));
    }

    /**
     * @return bool false when the container was already running (HTTP 304)
     *
     * @throws NotFoundException
     */
    public function start(string $nameOrId, ?string $detachKeys = null): bool
    {
        return ! $this->transport
            ->send(Operation::ContainerStart, ['name' => $nameOrId], ['detachKeys' => $detachKeys])
            ->isNotModified();
    }

    /**
     * @param int|null $timeout seconds to wait before killing (spec default 10)
     * @param bool     $ignore  do not fail if the container is already stopped
     *
     * @return bool false when the container was already stopped (HTTP 304)
     *
     * @throws NotFoundException
     */
    public function stop(string $nameOrId, ?int $timeout = null, bool $ignore = false): bool
    {
        // The spec renamed the query key from "Ignore" to "ignore" in 5.8.
        $ignoreKey = $this->transport->config()->apiVersion->isAtLeast(ApiVersion::V5_8) ? 'ignore' : 'Ignore';

        return ! $this->transport
            ->send(Operation::ContainerStop, ['name' => $nameOrId], ['timeout' => $timeout, $ignoreKey => $ignore ?: null])
            ->isNotModified();
    }

    /**
     * @param int|null $timeout seconds to wait before killing
     *
     * @throws NotFoundException
     */
    public function restart(string $nameOrId, ?int $timeout = null): void
    {
        $this->transport->send(Operation::ContainerRestart, ['name' => $nameOrId], ['timeout' => $timeout]);
    }

    /**
     * Send a signal (default SIGKILL).
     *
     * @param string|null $signal name ("SIGTERM") or number ("15")
     *
     * @throws NotFoundException
     * @throws ConflictException when the container is not running
     */
    public function kill(string $nameOrId, ?string $signal = null): void
    {
        $this->transport->send(Operation::ContainerKill, ['name' => $nameOrId], ['signal' => $signal]);
    }

    /**
     * @throws NotFoundException
     */
    public function pause(string $nameOrId): void
    {
        $this->transport->send(Operation::ContainerPause, ['name' => $nameOrId]);
    }

    /**
     * @throws NotFoundException
     */
    public function unpause(string $nameOrId): void
    {
        $this->transport->send(Operation::ContainerUnpause, ['name' => $nameOrId]);
    }

    /**
     * Prepare the container (storage, networking) without starting it (`podman init`).
     *
     * @return bool false when it was already initialised (HTTP 304)
     *
     * @throws NotFoundException
     */
    public function init(string $nameOrId): bool
    {
        return ! $this->transport->send(Operation::ContainerInit, ['name' => $nameOrId])->isNotModified();
    }

    /**
     * Block until the container meets a condition (default "exited") and return its exit code.
     *
     * @param list<string> $conditions e.g. ["running"], ["healthy"], ["exited", "stopped"]
     * @param string|null  $interval   polling interval as a Go duration (spec default "250ms")
     *
     * @throws NotFoundException
     */
    public function wait(string $nameOrId, array $conditions = [], ?string $interval = null): int
    {
        $result = $this->transport->send(
            Operation::ContainerWait,
            ['name' => $nameOrId],
            ['condition' => $conditions === [] ? null : $conditions, 'interval' => $interval],
        );

        $code = $result->json();

        return is_int($code) ? $code : throw HydrationException::unexpectedType('ContainerWaitLibpod', 'int', $code);
    }

    /**
     * Processes running in the container (`podman top`).
     *
     * @param list<string> $psArgs ps descriptors, e.g. ["pid", "user", "args"]
     *
     * @throws NotFoundException
     */
    public function top(string $nameOrId, array $psArgs = []): ContainerTop
    {
        $result = $this->transport->send(
            Operation::ContainerTop,
            ['name' => $nameOrId],
            ['ps_args' => $psArgs === [] ? null : $psArgs, 'stream' => false],
        );

        return ContainerTop::fromArray($result->jsonObject());
    }

    /**
     * Container output available at the time of the request (`podman logs` without --follow).
     *
     * Podman multiplexes stdout/stderr (also for TTY containers); frames are decoded per the stream format the spec
     * documents on ContainerAttachLibpod.
     *
     * @return list<LogLine>
     *
     * @throws NotFoundException
     */
    public function logs(string $nameOrId, ?ContainerLogsOptions $options = null): array
    {
        $options ??= new ContainerLogsOptions();
        $body = $this->transport->send(Operation::ContainerLogs, ['name' => $nameOrId], $options->toQuery())->text();

        $lines = [];
        foreach (MultiplexedStream::frames($body) as [$stream, $payload]) {
            array_push($lines, ...LogLine::fromFrame($stream, $payload, $options->timestamps));
        }

        return $lines;
    }

    /**
     * One resource-usage sample per container (`podman stats --no-stream`).
     *
     * The spec documents a single ContainerStats; Podman 5.8 wraps samples as {"Error": …, "Stats": [ContainerStats]}.
     * Both shapes are accepted.
     *
     * @param list<string> $namesOrIds empty = all running containers
     * @param bool         $all        include stopped containers (spec 5.8+)
     *
     * @return list<ContainerStats>
     *
     * @throws NotFoundException
     * @throws ServerException when Podman reports an error inside a 200 response
     */
    public function statsAll(array $namesOrIds = [], bool $all = false): array
    {
        $result = $this->transport->send(
            Operation::ContainersStatsAll,
            query: ['containers' => $namesOrIds === [] ? null : $namesOrIds, 'all' => $all ?: null, 'stream' => false],
        );

        $items = $result->jsonListOrObject();
        $report = $items[0] ?? null;
        if (count($items) === 1 && is_array($report) && array_key_exists('Stats', $report)) {
            $error = Data::errorOrNull($report, 'Error');
            if ($error !== null) {
                throw new ServerException(Operation::ContainersStatsAll->value, $result->status, $error);
            }
            $items = Data::list($report, 'Stats');
        }

        return Data::listOf($items, ContainerStats::fromArray(...));
    }

    /**
     * Remove stopped containers (`podman container prune`).
     *
     * @param Filters|null $filters label, annotation, until
     *
     * @return list<PruneReport>
     */
    public function prune(?Filters $filters = null): array
    {
        $result = $this->transport->send(Operation::ContainerPrune, query: ['filters' => $filters]);

        return Data::listOf($result->jsonList(), PruneReport::fromArray(...));
    }

    /**
     * @throws NotFoundException
     * @throws ConflictException when the new name is taken
     */
    public function rename(string $nameOrId, string $newName): void
    {
        $this->transport->send(Operation::ContainerRename, ['name' => $nameOrId], ['name' => $newName]);
    }

    /**
     * Export the container filesystem as a tar archive (`podman export`).
     *
     * @throws NotFoundException
     */
    public function export(string $nameOrId): StreamInterface
    {
        return $this->transport->send(Operation::ContainerExport, ['name' => $nameOrId])->stream();
    }

    /**
     * Run the container's healthcheck now (`podman healthcheck run`).
     *
     * @throws NotFoundException
     * @throws ConflictException when the container has no healthcheck or is not running
     */
    public function healthcheck(string $nameOrId): HealthCheckResults
    {
        return HealthCheckResults::fromArray(
            $this->transport->send(Operation::ContainerHealthcheck, ['name' => $nameOrId])->jsonObject(),
        );
    }

    /**
     * Mount the container's root filesystem on the host and return the mount path (`podman mount`).
     *
     * @throws NotFoundException
     */
    public function mount(string $nameOrId): string
    {
        $path = $this->transport->send(Operation::ContainerMount, ['name' => $nameOrId])->json();

        return is_string($path) ? $path : throw HydrationException::unexpectedType('ContainerMountLibpod', 'string', $path);
    }

    /**
     * @throws NotFoundException
     */
    public function unmount(string $nameOrId): void
    {
        $this->transport->send(Operation::ContainerUnmount, ['name' => $nameOrId]);
    }

    /**
     * Currently mounted containers.
     *
     * @return array<string, string> container ID → host mount path
     */
    public function mounted(): array
    {
        return Data::stringMap(['m' => $this->transport->send(Operation::ContainerShowMounted)->json()], 'm');
    }

    /**
     * Resize the container's TTY.
     *
     * @param bool $ignoreNotRunning do not fail when the container is not running (spec 5.8+, ignored by older servers)
     *
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function resize(string $nameOrId, int $height, int $width, bool $ignoreNotRunning = false): void
    {
        $this->transport->send(
            Operation::ContainerResize,
            ['name' => $nameOrId],
            ['h' => $height, 'w' => $width, 'running' => $ignoreNotRunning ?: null],
        );
    }

    /**
     * Update resource limits, healthcheck settings, environment or restart policy of an existing container.
     *
     * @param string|null $restartPolicy  no, always, on-failure, unless-stopped
     * @param int|null    $restartRetries only with "on-failure"
     *
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function update(
        string $nameOrId,
        ?ContainerUpdateRequest $request = null,
        ?string $restartPolicy = null,
        ?int $restartRetries = null,
    ): void {
        $this->transport->send(
            Operation::ContainerUpdate,
            ['name' => $nameOrId],
            ['restartPolicy' => $restartPolicy, 'restartRetries' => $restartRetries],
            $request ?? [],
        );
    }

    /**
     * Copy a path out of the container as a tar archive (`podman cp CONTAINER:PATH -`).
     *
     * @param array<string, string>|null $rename paths to rename inside the archive
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws JsonException
     */
    public function getArchive(string $nameOrId, string $path, ?array $rename = null): StreamInterface
    {
        return $this->transport->send(
            Operation::ContainerArchive,
            ['name' => $nameOrId],
            ['path' => $path, 'rename' => Query::json($rename)],
        )->stream();
    }

    /**
     * Extract a tar archive into a directory of the container (`podman cp - CONTAINER:PATH`).
     *
     * @param bool|null $pause pause the container while copying (spec default true)
     *
     * @throws BadRequestException
     * @throws ForbiddenException  when the destination is on a read-only filesystem
     * @throws NotFoundException
     */
    public function putArchive(string $nameOrId, string $path, StreamInterface|string $tar, ?bool $pause = null): void
    {
        $this->transport->send(
            Operation::PutContainerArchive,
            ['name' => $nameOrId],
            ['path' => $path, 'pause' => $pause],
            $tar,
            contentType: 'application/x-tar',
        );
    }

    /**
     * Create an image from a container's changes (`podman commit`).
     *
     * @throws NotFoundException
     */
    public function commit(string $nameOrId, ?ContainerCommitOptions $options = null): void
    {
        $this->transport->send(Operation::ImageCommit, query: ['container' => $nameOrId, ...($options?->toQuery() ?? [])]);
    }
}
