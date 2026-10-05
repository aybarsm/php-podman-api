<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Kube;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Kube::generate() (GenerateKubeLibpod).
 */
final readonly class KubeGenerateOptions implements QueryParameters
{
    public function __construct(
        /** Also generate a Kubernetes Service object */
        public ?bool $service = null,
        /** Kubernetes kind to generate (spec default "pod") */
        public ?string $type = null,
        /** Replica count for the Deployment kind */
        public ?int $replicas = null,
        /** Do not truncate annotations to the Kubernetes maximum of 63 characters */
        public ?bool $noTrunc = null,
        /** Add Podman-only reserved annotations (not usable by Kubernetes) */
        public ?bool $podmanOnly = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'service' => $this->service,
            'type' => $this->type,
            'replicas' => $this->replicas,
            'noTrunc' => $this->noTrunc,
            'podmanOnly' => $this->podmanOnly,
        ];
    }
}
