<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Manifest;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for the deprecated Manifests::add() (ManifestAddLibpod, definition ManifestAddOptions).
 */
final readonly class ManifestAddRequest implements RequestBody
{
    /**
     * @param list<string>               $images           image references to add to the list
     * @param array<string, string>|null $annotations      annotations for the added item(s)
     * @param array<string, string>|null $indexAnnotations annotations for the manifest list itself
     * @param list<string>|null          $features
     * @param list<string>|null          $osFeatures
     * @param array<string, mixed>       $extra            any other ManifestAddOptions field, sent verbatim (merged last)
     */
    public function __construct(
        public array $images,
        /** Include all images when an added reference is itself a list */
        public ?bool $all = null,
        public ?array $annotations = null,
        public ?array $indexAnnotations = null,
        public ?string $arch = null,
        public ?string $os = null,
        public ?string $osVersion = null,
        public ?array $osFeatures = null,
        public ?string $variant = null,
        public ?array $features = null,
        /** Subject value to set on the manifest list itself */
        public ?string $subject = null,
        public array $extra = [],
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'images' => $this->images,
            'all' => $this->all,
            'annotations' => $this->annotations,
            'index_annotations' => $this->indexAnnotations,
            'arch' => $this->arch,
            'os' => $this->os,
            'os_version' => $this->osVersion,
            'os_features' => $this->osFeatures,
            'variant' => $this->variant,
            'features' => $this->features,
            'subject' => $this->subject,
            ...$this->extra,
        ];
    }
}
