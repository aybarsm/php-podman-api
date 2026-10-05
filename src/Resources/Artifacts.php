<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Artifact\ArtifactAddOptions;
use Aybarsm\Podman\Api\Dto\Artifact\ArtifactInspect;
use Aybarsm\Podman\Api\Dto\Artifact\ArtifactSummary;
use Aybarsm\Podman\Api\Dto\Shared\RegistryAuth;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\UnauthorizedException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;
use JsonException;
use Psr\Http\Message\StreamInterface;

/**
 * Libpod `artifacts` operations (OCI artifacts, `podman artifact`). Every operation needs Podman 5.6 or newer.
 */
final readonly class Artifacts extends AbstractResource
{
    /**
     * List artifacts in local storage (`podman artifact ls`).
     *
     * @since Podman 5.6
     *
     * @return list<ArtifactSummary>
     */
    public function list(): array
    {
        return Data::listOf($this->transport->send(Operation::ArtifactList)->jsonList(), ArtifactSummary::fromArray(...));
    }

    /**
     * @since Podman 5.6
     *
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId): ArtifactInspect
    {
        return ArtifactInspect::fromArray($this->transport->send(Operation::ArtifactInspect, ['name' => $nameOrId])->jsonObject());
    }

    /**
     * Upload a file as a new artifact, or append it to an existing one (`podman artifact add`); returns the artifact digest.
     *
     * @param string                 $name     artifact reference, e.g. "quay.io/org/artifact:tag"
     * @param string                 $fileName name (title) of the file inside the artifact
     * @param StreamInterface|string $content  the file contents
     *
     * @since Podman 5.6
     *
     * @throws BadRequestException
     * @throws NotFoundException  when appending to an artifact that does not exist
     */
    public function add(string $name, string $fileName, StreamInterface|string $content, ?ArtifactAddOptions $options = null): string
    {
        $result = $this->transport->send(
            Operation::ArtifactAdd,
            query: ['name' => $name, 'fileName' => $fileName, ...($options?->toQuery() ?? [])],
            body: $content,
            contentType: 'application/octet-stream',
        );

        return Data::string($result->jsonObject(), 'ArtifactDigest');
    }

    /**
     * Add a file that already exists on the server's filesystem as an artifact; returns the artifact digest.
     *
     * @param string $name     artifact reference, e.g. "quay.io/org/artifact:tag"
     * @param string $path     absolute path of the file on the Podman host
     * @param string $fileName name (title) of the file inside the artifact
     *
     * @since Podman 5.8
     *
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function addLocal(string $name, string $path, string $fileName, ?ArtifactAddOptions $options = null): string
    {
        $result = $this->transport->send(
            Operation::ArtifactLocal,
            query: ['name' => $name, 'path' => $path, 'fileName' => $fileName, ...($options?->toQuery() ?? [])],
        );

        return Data::string($result->jsonObject(), 'ArtifactDigest');
    }

    /**
     * Remove an artifact (`podman artifact rm`).
     *
     * @return list<string> digests of the removed artifacts
     *
     * @since Podman 5.6
     *
     * @throws NotFoundException
     */
    public function remove(string $nameOrId): array
    {
        $result = $this->transport->send(Operation::ArtifactDelete, ['name' => $nameOrId]);

        return Data::stringList($result->jsonObject(), 'ArtifactDigests');
    }

    /**
     * Remove several artifacts, or all of them (`podman artifact rm a b`, `podman artifact rm --all`).
     *
     * @param list<string> $artifacts names or IDs; ignored when $all is true
     * @param bool         $ignore    do not fail for artifacts that do not exist
     *
     * @return list<string> digests of the removed artifacts
     *
     * @since Podman 5.7
     *
     * @throws NotFoundException
     */
    public function removeMany(array $artifacts = [], bool $all = false, bool $ignore = false): array
    {
        $result = $this->transport->send(
            Operation::ArtifactDeleteAll,
            query: ['artifacts' => $artifacts === [] ? null : $artifacts, 'all' => $all ?: null, 'ignore' => $ignore ?: null],
        );

        return Data::stringList($result->jsonObject(), 'ArtifactDigests');
    }

    /**
     * Extract the artifact's files as a tar archive (`podman artifact extract`).
     *
     * @param string|null $title        only the file with this title
     * @param string|null $digest       only the file with this digest
     * @param bool        $excludeTitle for a single file, do not use its title as the file name inside the archive
     *
     * @since Podman 5.6
     *
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function extract(string $nameOrDigest, ?string $title = null, ?string $digest = null, bool $excludeTitle = false): StreamInterface
    {
        return $this->transport->send(
            Operation::ArtifactExtract,
            ['name' => $nameOrDigest],
            ['title' => $title, 'digest' => $digest, 'excludeTitle' => $excludeTitle ?: null],
        )->stream();
    }

    /**
     * Pull an artifact from a registry (`podman artifact pull`); returns the artifact digest.
     *
     * @param bool|null   $tlsVerify  require TLS verification (spec default true)
     * @param int|null    $retry      retries on failure (spec default 3)
     * @param string|null $retryDelay delay between retries as a Go duration (spec default "1s")
     *
     * @since Podman 5.6
     *
     * @throws BadRequestException
     * @throws UnauthorizedException
     * @throws NotFoundException
     * @throws JsonException
     */
    public function pull(
        string $name,
        ?RegistryAuth $auth = null,
        ?bool $tlsVerify = null,
        ?int $retry = null,
        ?string $retryDelay = null,
    ): string {
        $result = $this->transport->send(
            Operation::ArtifactPull,
            query: ['name' => $name, 'retry' => $retry, 'retryDelay' => $retryDelay, 'tlsVerify' => $tlsVerify],
            headers: RegistryAuth::headers($auth),
        );

        return Data::string($result->jsonObject(), 'ArtifactDigest');
    }

    /**
     * Push an artifact to a registry (`podman artifact push`); returns the artifact digest.
     *
     * @param bool|null   $tlsVerify  require TLS verification (spec default true)
     * @param int|null    $retry      retries on failure (spec default 3)
     * @param string|null $retryDelay delay between retries as a Go duration (spec default "1s")
     *
     * @since Podman 5.6
     *
     * @throws BadRequestException
     * @throws UnauthorizedException
     * @throws NotFoundException
     * @throws JsonException
     */
    public function push(
        string $name,
        ?RegistryAuth $auth = null,
        ?bool $tlsVerify = null,
        ?int $retry = null,
        ?string $retryDelay = null,
    ): string {
        $result = $this->transport->send(
            Operation::ArtifactPush,
            ['name' => $name],
            ['retry' => $retry, 'retryDelay' => $retryDelay, 'tlsVerify' => $tlsVerify],
            headers: RegistryAuth::headers($auth),
        );

        return Data::string($result->jsonObject(), 'ArtifactDigest');
    }
}
