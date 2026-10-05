<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Manifest;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Manifests::push() (ManifestPushLibpod).
 *
 * `quiet` is not offered: the spec's default (true) is what makes Podman answer with the documented IDResponse.
 */
final readonly class ManifestPushOptions implements QueryParameters
{
    /**
     * @param list<string>|null $addCompression add existing instances with these compression algorithms to the list
     */
    public function __construct(
        /** Push all images (spec default true) */
        public ?bool $all = null,
        public ?array $addCompression = null,
        /** Always compress with the requested format; never reuse differently compressed blobs on the registry */
        public ?bool $forceCompressionFormat = null,
        /** Require HTTPS and verify signatures when contacting registries (spec default true) */
        public ?bool $tlsVerify = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'addCompression' => $this->addCompression,
            'forceCompressionFormat' => $this->forceCompressionFormat,
            'all' => $this->all,
            'tlsVerify' => $this->tlsVerify,
        ];
    }
}
