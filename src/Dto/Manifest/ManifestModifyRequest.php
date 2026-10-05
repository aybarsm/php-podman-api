<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Manifest;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Aybarsm\Podman\Api\Enums\ManifestOperation;
use Override;

/**
 * Body for Manifests::modify() and the optional body of Manifests::create() (definition ManifestModifyOptions).
 *
 * Operation "update" uses all fields, "remove" uses only `images`, "annotate" uses only `annotations`.
 * Leave `operation` null for create().
 */
final readonly class ManifestModifyRequest implements RequestBody
{
    /**
     * @param list<string>|null          $images              image references to add, or instance digests to remove
     * @param array<string, string>|null $annotations         annotations for the item(s) in the list
     * @param array<string, string>|null $indexAnnotations    annotations for the manifest list itself
     * @param list<string>|null          $features            feature list for the item
     * @param list<string>|null          $osFeatures          OS features for the item
     * @param list<string>|null          $artifactFiles       files to add as an artifact
     * @param array<string, string>|null $artifactAnnotations
     * @param array<string, mixed>       $extra               any other ManifestModifyOptions field, sent verbatim (merged last)
     */
    public function __construct(
        public ?ManifestOperation $operation = null,
        public ?array $images = null,
        /** Include all images when an added reference is itself a list */
        public ?bool $all = null,
        public ?array $annotations = null,
        public ?array $indexAnnotations = null,
        /** Architecture override for the item */
        public ?string $arch = null,
        /** OS override for the item */
        public ?string $os = null,
        public ?string $osVersion = null,
        public ?array $osFeatures = null,
        public ?string $variant = null,
        public ?array $features = null,
        /** Subject value to set on the manifest list itself */
        public ?string $subject = null,
        public ?string $artifactType = null,
        public ?string $artifactConfigType = null,
        public ?string $artifactConfig = null,
        public ?string $artifactLayerType = null,
        public ?bool $artifactExcludeTitles = null,
        public ?string $artifactSubject = null,
        public ?array $artifactAnnotations = null,
        public ?array $artifactFiles = null,
        public array $extra = [],
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'operation' => $this->operation?->value,
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
            'artifact_type' => $this->artifactType,
            'artifact_config_type' => $this->artifactConfigType,
            'artifact_config' => $this->artifactConfig,
            'artifact_layer_type' => $this->artifactLayerType,
            'artifact_exclude_titles' => $this->artifactExcludeTitles,
            'artifact_subject' => $this->artifactSubject,
            'artifact_annotations' => $this->artifactAnnotations,
            'artifact_files' => $this->artifactFiles,
            ...$this->extra,
        ];
    }
}
