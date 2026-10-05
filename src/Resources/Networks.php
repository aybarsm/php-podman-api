<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Network\Network;
use Aybarsm\Podman\Api\Dto\Network\NetworkConnectRequest;
use Aybarsm\Podman\Api\Dto\Network\NetworkCreateRequest;
use Aybarsm\Podman\Api\Dto\Network\NetworkInspect;
use Aybarsm\Podman\Api\Dto\Network\NetworkPruneReport;
use Aybarsm\Podman\Api\Dto\Network\NetworkRemoveReport;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;

/**
 * Libpod `networks` operations.
 */
final readonly class Networks extends AbstractResource
{
    /**
     * List networks (`podman network ls`).
     *
     * @param Filters|null $filters name, id, driver, label, until
     *
     * @return list<Network>
     */
    public function list(?Filters $filters = null): array
    {
        $result = $this->transport->send(Operation::NetworkList, query: ['filters' => $filters]);

        return Data::listOf($result->jsonList(), Network::fromArray(...));
    }

    /**
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId): NetworkInspect
    {
        return NetworkInspect::fromArray(
            $this->transport->send(Operation::NetworkInspect, ['name' => $nameOrId])->jsonObject(),
        );
    }

    public function exists(string $nameOrId): bool
    {
        return $this->probe(Operation::NetworkExists, ['name' => $nameOrId]);
    }

    /**
     * Create a network (`podman network create`).
     *
     * @param bool $ignoreIfExists return the existing network instead of failing (spec 5.8+, ignored by older servers)
     *
     * @throws BadRequestException
     * @throws ConflictException when the name is already in use
     */
    public function create(NetworkCreateRequest $request, bool $ignoreIfExists = false): Network
    {
        $result = $this->transport->send(
            Operation::NetworkCreate,
            query: ['ignoreIfExists' => $ignoreIfExists ?: null],
            body: $request,
        );

        return Network::fromArray($result->jsonObject());
    }

    /**
     * Remove a network (`podman network rm`).
     *
     * @param bool $force also remove the containers attached to it
     *
     * @return list<NetworkRemoveReport>
     *
     * @throws NotFoundException
     */
    public function remove(string $nameOrId, bool $force = false): array
    {
        $result = $this->transport->send(Operation::NetworkDelete, ['name' => $nameOrId], ['force' => $force ?: null]);

        return Data::listOf($result->jsonList(), NetworkRemoveReport::fromArray(...));
    }

    /**
     * Connect a container to the network (`podman network connect`).
     *
     * @throws NotFoundException
     */
    public function connect(string $nameOrId, NetworkConnectRequest $request): void
    {
        $this->transport->send(Operation::NetworkConnect, ['name' => $nameOrId], body: $request);
    }

    /**
     * Disconnect a container from the network (`podman network disconnect`).
     *
     * @param string $container container name or ID
     *
     * @throws NotFoundException
     */
    public function disconnect(string $nameOrId, string $container, bool $force = false): void
    {
        $this->transport->send(
            Operation::NetworkDisconnect,
            ['name' => $nameOrId],
            body: ['Container' => $container, 'Force' => $force ?: null],
        );
    }

    /**
     * Add or remove DNS servers of an existing network (`podman network update`).
     *
     * @param list<string>|null $addDnsServers
     * @param list<string>|null $removeDnsServers
     *
     * @throws BadRequestException
     */
    public function update(string $nameOrId, ?array $addDnsServers = null, ?array $removeDnsServers = null): void
    {
        $this->transport->send(
            Operation::NetworkUpdate,
            ['name' => $nameOrId],
            body: ['adddnsservers' => $addDnsServers, 'removednsservers' => $removeDnsServers],
        );
    }

    /**
     * Remove networks without containers (`podman network prune`).
     *
     * @param Filters|null $filters until, label (label!=…)
     *
     * @return list<NetworkPruneReport>
     */
    public function prune(?Filters $filters = null): array
    {
        $result = $this->transport->send(Operation::NetworkPrune, query: ['filters' => $filters]);

        return Data::listOf($result->jsonList(), NetworkPruneReport::fromArray(...));
    }
}
