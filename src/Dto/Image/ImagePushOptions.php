<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Images::push() (ImagePushLibpod), excluding the image name.
 */
final readonly class ImagePushOptions implements QueryParameters
{
    public function __construct(
        /** Push to this destination instead of the one the image name refers to */
        public ?string $destination = null,
        /** Manifest type: "oci", "v2s1" or "v2s2" (default: the source's type, with fallbacks) */
        public ?string $format = null,
        /** Push all images related to the image list */
        public ?bool $all = null,
        /** Compression format for the image layers */
        public ?string $compressionFormat = null,
        public ?int $compressionLevel = null,
        /** Always compress with compressionFormat; never reuse differently compressed blobs on the registry */
        public ?bool $forceCompressionFormat = null,
        /** Discard any pre-existing signatures */
        public ?bool $removeSignatures = null,
        /** Require TLS verification (spec default true) */
        public ?bool $tlsVerify = null,
        /** Silence progress output (spec default true) */
        public ?bool $quiet = null,
        /** Number of retries on failure */
        public ?int $retry = null,
        /** Delay between retries as a Go duration, e.g. "412ms", "3.5h" */
        public ?string $retryDelay = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'destination' => $this->destination,
            'forceCompressionFormat' => $this->forceCompressionFormat,
            'compressionFormat' => $this->compressionFormat,
            'compressionLevel' => $this->compressionLevel,
            'tlsVerify' => $this->tlsVerify,
            'quiet' => $this->quiet,
            'format' => $this->format,
            'all' => $this->all,
            'removeSignatures' => $this->removeSignatures,
            'retry' => $this->retry,
            'retryDelay' => $this->retryDelay,
        ];
    }
}
