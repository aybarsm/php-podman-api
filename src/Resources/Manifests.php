<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Image\ImageRemoveReport;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestAddRequest;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestList;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestModifyReport;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestModifyRequest;
use Aybarsm\Podman\Api\Dto\Manifest\ManifestPushOptions;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;

/**
 * Libpod `manifests` operations (manifest lists / image indexes).
 */
final readonly class Manifests extends AbstractResource
{
    /**
     * Create a manifest list (`podman manifest create`); returns its ID.
     *
     * @param string       $name   name of the list to create
     * @param list<string> $images images or manifest lists to add
     * @param bool         $all    add all contents when an image is itself a list
     * @param bool         $amend  modify an existing list with that name instead of failing
     *
     * @throws BadRequestException
     * @throws NotFoundException   when an image does not exist
     */
    public function create(
        string $name,
        array $images,
        bool $all = false,
        bool $amend = false,
        ?ManifestModifyRequest $options = null,
    ): string {
        $result = $this->transport->send(
            Operation::ManifestCreate,
            ['name' => $name],
            ['images' => $images, 'all' => $all ?: null, 'amend' => $amend ?: null],
            $options,
        );

        return Data::string($result->jsonObject(), 'Id');
    }

    /**
     * @param bool|null $tlsVerify require HTTPS and verify signatures when contacting registries (spec default true)
     *
     * @throws NotFoundException
     */
    public function inspect(string $name, ?bool $tlsVerify = null): ManifestList
    {
        $result = $this->transport->send(Operation::ManifestInspect, ['name' => $name], ['tlsVerify' => $tlsVerify]);

        return ManifestList::fromArray($result->jsonObject());
    }

    public function exists(string $name): bool
    {
        return $this->probe(Operation::ManifestExists, ['name' => $name]);
    }

    /**
     * Add images to, remove instances from, or annotate a manifest list (`podman manifest add/remove/annotate`).
     *
     * Operations are not atomic when several images are given. Podman answers a partial failure with HTTP 409,
     * which surfaces as ConflictException.
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function modify(string $name, ManifestModifyRequest $request, ?bool $tlsVerify = null): ManifestModifyReport
    {
        $result = $this->transport->send(Operation::ManifestModify, ['name' => $name], ['tlsVerify' => $tlsVerify], $request);

        return ManifestModifyReport::fromArray($result->jsonObject());
    }

    /**
     * Add an image to a manifest list; returns the list ID.
     *
     * @deprecated Podman 4.0 deprecated this endpoint; use modify() with ManifestOperation::Update
     *
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function add(string $name, ManifestAddRequest $request): string
    {
        $result = $this->transport->send(Operation::ManifestAdd, ['name' => $name], body: $request);

        return Data::string($result->jsonObject(), 'Id');
    }

    /**
     * Delete a manifest list (`podman manifest rm`).
     *
     * @param bool $ignore do not fail when the list does not exist
     *
     * @throws NotFoundException
     */
    public function remove(string $name, bool $ignore = false): ImageRemoveReport
    {
        $result = $this->transport->send(Operation::ManifestDelete, ['name' => $name], ['ignore' => $ignore ?: null]);

        return ImageRemoveReport::fromArray($result->jsonObject());
    }

    /**
     * Push a manifest list to a registry (`podman manifest push`); returns the ID Podman reports.
     *
     * @param string $destination destination reference, e.g. "quay.io/me/app:latest"
     *
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function push(string $name, string $destination, ?ManifestPushOptions $options = null): string
    {
        $result = $this->transport->send(
            Operation::ManifestPush,
            ['name' => $name, 'destination' => $destination],
            $options?->toQuery() ?? [],
        );

        return Data::string($result->jsonObject(), 'Id');
    }

    /**
     * Push a manifest list through the pre-4.0 endpoint; returns the ID Podman reports.
     *
     * @deprecated Podman 4.0 deprecated this endpoint; use push()
     *
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function pushV3(string $name, string $destination, bool $all = false): string
    {
        $result = $this->transport->send(
            Operation::ManifestPushV3,
            ['name' => $name],
            ['destination' => $destination, 'all' => $all ?: null],
        );

        return Data::string($result->jsonObject(), 'Id');
    }
}
