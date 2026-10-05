<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Image\ImageHistoryEntry;
use Aybarsm\Podman\Api\Dto\Image\ImageImportOptions;
use Aybarsm\Podman\Api\Dto\Image\ImageInspect;
use Aybarsm\Podman\Api\Dto\Image\ImagePruneOptions;
use Aybarsm\Podman\Api\Dto\Image\ImagePullOptions;
use Aybarsm\Podman\Api\Dto\Image\ImagePullReport;
use Aybarsm\Podman\Api\Dto\Image\ImagePushOptions;
use Aybarsm\Podman\Api\Dto\Image\ImageRemoveManyOptions;
use Aybarsm\Podman\Api\Dto\Image\ImageRemoveReport;
use Aybarsm\Podman\Api\Dto\Image\ImageSearchOptions;
use Aybarsm\Podman\Api\Dto\Image\ImageSearchResult;
use Aybarsm\Podman\Api\Dto\Image\ImageSummary;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Shared\PruneReport;
use Aybarsm\Podman\Api\Dto\Shared\RegistryAuth;
use Aybarsm\Podman\Api\Exceptions\BadRequestException;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\HydrationException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;
use JsonException;
use Psr\Http\Message\StreamInterface;

/**
 * Libpod `images` operations.
 *
 * Deferred (see dev-tools/deferred-operations.php): build (ImageBuild, LocalBuild), changes, resolve.
 * Committing a container to an image lives in Containers::commit().
 */
final readonly class Images extends AbstractResource
{
    private const string TAR = 'application/x-tar';

    /**
     * List images (`podman images`).
     *
     * @param Filters|null $filters before, dangling, label, reference, id, since
     * @param bool         $all     include intermediate images (default: final layers only)
     *
     * @return list<ImageSummary>
     */
    public function list(?Filters $filters = null, bool $all = false): array
    {
        $result = $this->transport->send(Operation::ImageList, query: ['all' => $all ?: null, 'filters' => $filters]);

        return Data::listOf($result->jsonList(), ImageSummary::fromArray(...));
    }

    /**
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId): ImageInspect
    {
        return ImageInspect::fromArray($this->transport->send(Operation::ImageInspect, ['name' => $nameOrId])->jsonObject());
    }

    public function exists(string $nameOrId): bool
    {
        return $this->probe(Operation::ImageExists, ['name' => $nameOrId]);
    }

    /**
     * Remove an image from local storage (`podman rmi`).
     *
     * @param bool $force          also remove containers using it, and remove it even if it has other tags
     * @param bool $ignore         do not fail when the image does not exist (spec 5.8+, ignored by older servers)
     * @param bool $lookupManifest resolve to a manifest list instead of an image (spec 5.8+, ignored by older servers)
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException   when in use and not forced
     */
    public function remove(string $nameOrId, bool $force = false, bool $ignore = false, bool $lookupManifest = false): ImageRemoveReport
    {
        $result = $this->transport->send(
            Operation::ImageDelete,
            ['name' => $nameOrId],
            ['force' => $force ?: null, 'ignore' => $ignore ?: null, 'lookupManifest' => $lookupManifest ?: null],
        );

        return ImageRemoveReport::fromArray($result->jsonObject());
    }

    /**
     * Remove several (or all) images in one call (`podman rmi IMAGE…` / `podman rmi --all`).
     *
     * Failures for individual images are reported in ImageRemoveReport::$errors.
     *
     * @throws BadRequestException
     */
    public function removeMany(ImageRemoveManyOptions $options): ImageRemoveReport
    {
        return ImageRemoveReport::fromArray($this->transport->send(Operation::ImageDeleteAll, query: $options->toQuery())->jsonObject());
    }

    /**
     * Remove unused images (`podman image prune`).
     *
     * @return list<PruneReport>
     */
    public function prune(?ImagePruneOptions $options = null): array
    {
        $result = $this->transport->send(Operation::ImagePrune, query: $options?->toQuery() ?? []);

        return Data::listOf($result->jsonList(), PruneReport::fromArray(...));
    }

    /**
     * Parent layers of an image (`podman image history`), newest first.
     *
     * @return list<ImageHistoryEntry>
     *
     * @throws NotFoundException
     */
    public function history(string $nameOrId): array
    {
        $result = $this->transport->send(Operation::ImageHistory, ['name' => $nameOrId]);

        return Data::listOf($result->jsonListOrObject(), ImageHistoryEntry::fromArray(...));
    }

    /**
     * Render the image's layer tree (`podman image tree`).
     *
     * @param bool $whatRequires show the child images and layers of the image instead
     *
     * @throws NotFoundException
     */
    public function tree(string $nameOrId, bool $whatRequires = false): string
    {
        $result = $this->transport->send(Operation::ImageTree, ['name' => $nameOrId], ['whatrequires' => $whatRequires ?: null]);

        return Data::string($result->jsonObject(), 'Tree');
    }

    /**
     * Add a name to an image (`podman tag`).
     *
     * @param string      $repo repository, e.g. "quay.io/me/app"
     * @param string|null $tag  tag within the repository
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function tag(string $nameOrId, string $repo, ?string $tag = null): void
    {
        $this->transport->send(Operation::ImageTag, ['name' => $nameOrId], ['repo' => $repo, 'tag' => $tag]);
    }

    /**
     * Remove a name from an image (`podman untag`). Without repo and tag, every name is removed.
     *
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function untag(string $nameOrId, ?string $repo = null, ?string $tag = null): void
    {
        $this->transport->send(Operation::ImageUntag, ['name' => $nameOrId], ['repo' => $repo, 'tag' => $tag]);
    }

    /**
     * Pull an image from a registry (`podman pull`).
     *
     * The progress stream is buffered and folded into one report. Podman can report a failure after HTTP 200:
     * check ImagePullReport::failed().
     *
     * @param string $reference image reference, e.g. "quay.io/libpod/alpine:latest"
     *
     * @throws BadRequestException
     * @throws JsonException
     */
    public function pull(string $reference, ?ImagePullOptions $options = null, ?RegistryAuth $auth = null): ImagePullReport
    {
        $result = $this->transport->send(
            Operation::ImagePull,
            query: ['reference' => $reference, ...($options?->toQuery() ?? [])],
            headers: RegistryAuth::headers($auth),
        );

        return ImagePullReport::fromDocuments($result->jsonDocuments());
    }

    /**
     * Push an image to a registry (`podman push`).
     *
     * @return string the raw response body: the spec documents it only as binary progress output
     *
     * @throws NotFoundException
     * @throws JsonException
     */
    public function push(string $nameOrId, ?ImagePushOptions $options = null, ?RegistryAuth $auth = null): string
    {
        return $this->transport->send(
            Operation::ImagePush,
            ['name' => $nameOrId],
            $options?->toQuery() ?? [],
            headers: RegistryAuth::headers($auth),
        )->text();
    }

    /**
     * Search registries for images (`podman search`).
     *
     * @return list<ImageSearchResult>
     */
    public function search(string $term, ?ImageSearchOptions $options = null): array
    {
        $result = $this->transport->send(Operation::ImageSearch, query: ['term' => $term, ...($options?->toQuery() ?? [])]);

        return Data::listOf($result->jsonListOrObject(), ImageSearchResult::fromArray(...));
    }

    /**
     * Export one image as a tar archive (`podman save`).
     *
     * @param string|null $format archive format, e.g. "docker-archive", "oci-archive"
     *
     * @throws NotFoundException
     */
    public function save(string $nameOrId, ?string $format = null, bool $compress = false): StreamInterface
    {
        return $this->transport->send(
            Operation::ImageGet,
            ['name' => $nameOrId],
            ['format' => $format, 'compress' => $compress ?: null],
        )->stream();
    }

    /**
     * Export several images into one archive (`podman save IMAGE…`). Only "docker-archive" is supported.
     *
     * @param list<string> $references images to export
     *
     * @throws NotFoundException
     */
    public function saveMany(
        array $references,
        ?string $format = null,
        bool $compress = false,
        bool $ociAcceptUncompressedLayers = false,
    ): StreamInterface {
        return $this->transport->send(Operation::ImageExport, query: [
            'format' => $format,
            'references' => $references,
            'compress' => $compress ?: null,
            'ociAcceptUncompressedLayers' => $ociAcceptUncompressedLayers ?: null,
        ])->stream();
    }

    /**
     * Load images from an oci-archive or docker-archive tarball (`podman load`).
     *
     * @return list<string> names of the loaded images
     *
     * @throws BadRequestException
     */
    public function load(StreamInterface|string $tar): array
    {
        $result = $this->transport->send(Operation::ImageLoad, body: $tar, contentType: self::TAR);

        return Data::stringList($result->jsonObject(), 'Names');
    }

    /**
     * Load images from an archive file that already exists on the Podman host.
     *
     * @param string $path absolute path to the archive on the server's filesystem
     *
     * @return list<string> names of the loaded images
     *
     * @throws BadRequestException
     * @throws NotFoundException
     *
     * @since Podman 5.7
     */
    public function loadLocal(string $path): array
    {
        $result = $this->transport->send(Operation::LocalImages, query: ['path' => $path]);

        return Data::stringList($result->jsonObject(), 'Names');
    }

    /**
     * Create an image from a filesystem tarball (`podman import`); returns the new image ID.
     *
     * @param StreamInterface|string|null $tar the tarball; pass null when ImageImportOptions::$url is set
     *
     * @throws BadRequestException
     */
    public function import(StreamInterface|string|null $tar, ?ImageImportOptions $options = null): string
    {
        $result = $this->transport->send(
            Operation::ImageImport,
            query: $options?->toQuery() ?? [],
            body: $tar ?? '',
            contentType: self::TAR,
        );

        return Data::string($result->jsonObject(), 'Id');
    }

    /**
     * Copy an image to another host (`podman image scp`); returns the reported image ID.
     *
     * @param string      $source      source connection/image
     * @param string|null $destination destination connection/image
     *
     * @throws BadRequestException
     */
    public function scp(string $source, ?string $destination = null, bool $quiet = false): string
    {
        $result = $this->transport->send(
            Operation::ImageScp,
            ['name' => $source],
            ['destination' => $destination, 'quiet' => $quiet ?: null],
        );

        return Data::string($result->jsonObject(), 'Id');
    }
}
