<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Secret\SecretCreateOptions;
use Aybarsm\Podman\Api\Dto\Secret\SecretInfo;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;

/**
 * Libpod `secrets` operations.
 */
final readonly class Secrets extends AbstractResource
{
    /**
     * List secrets (`podman secret ls`). Secret values are never included.
     *
     * @param Filters|null $filters name (regex), id (full or partial)
     *
     * @return list<SecretInfo>
     */
    public function list(?Filters $filters = null): array
    {
        $result = $this->transport->send(Operation::SecretList, query: ['filters' => $filters]);

        return Data::listOf($result->jsonList(), SecretInfo::fromArray(...));
    }

    /**
     * @param bool $showSecret include the secret value in SecretInfo::$secretData
     *
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId, bool $showSecret = false): SecretInfo
    {
        $result = $this->transport->send(Operation::SecretInspect, ['name' => $nameOrId], ['showsecret' => $showSecret ?: null]);

        return SecretInfo::fromArray($result->jsonObject());
    }

    public function exists(string $nameOrId): bool
    {
        return $this->probe(Operation::SecretExists, ['name' => $nameOrId]);
    }

    /**
     * Create a secret (`podman secret create`); returns its ID.
     *
     * @param string $data the secret value, sent as the request body
     */
    public function create(string $name, string $data, ?SecretCreateOptions $options = null): string
    {
        $result = $this->transport->send(
            Operation::SecretCreate,
            query: ['name' => $name, ...($options?->toQuery() ?? [])],
            body: $data,
        );

        return Data::string($result->jsonObject(), 'ID');
    }

    /**
     * Remove a secret (`podman secret rm`).
     *
     * @param bool $all remove every secret, not just $nameOrId
     *
     * @throws NotFoundException
     */
    public function remove(string $nameOrId, bool $all = false): void
    {
        $this->transport->send(Operation::SecretDelete, ['name' => $nameOrId], ['all' => $all ?: null]);
    }
}
