<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Image;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Images::pull() (ImagePullLibpod), excluding the reference.
 *
 * `compatMode` is not offered: it switches the response to the Docker-compat payload, which ImagePullReport
 * does not describe.
 */
final readonly class ImagePullOptions implements QueryParameters
{
    public function __construct(
        /** Pull policy: "always" (default), "missing", "newer", "never" */
        public ?string $policy = null,
        /** Pull all tagged images in the repository */
        public ?bool $allTags = null,
        public ?string $arch = null,
        public ?string $os = null,
        public ?string $variant = null,
        /** Require TLS verification (spec default true) */
        public ?bool $tlsVerify = null,
        /** Silence progress output; cannot be combined with pullProgress */
        public ?bool $quiet = null,
        /**
         * Send progress reports; cannot be combined with quiet.
         *
         * @since Podman 5.8 (spec)
         */
        public ?bool $pullProgress = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'quiet' => $this->quiet,
            'pullProgress' => $this->pullProgress,
            'Arch' => $this->arch,
            'OS' => $this->os,
            'Variant' => $this->variant,
            'policy' => $this->policy,
            'tlsVerify' => $this->tlsVerify,
            'allTags' => $this->allTags,
        ];
    }
}
