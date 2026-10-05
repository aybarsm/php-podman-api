<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Kube;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Kube::apply() (KubeApplyLibpod).
 */
final readonly class KubeApplyOptions implements QueryParameters
{
    public function __construct(
        /** Path (on the Podman host) to the CA cert file of the Kubernetes cluster */
        public ?string $caCertFile = null,
        /** Path (on the Podman host) to the kubeconfig file of the Kubernetes cluster */
        public ?string $kubeConfig = null,
        /** Kubernetes namespace to deploy to */
        public ?string $namespace = null,
        /** Create a service object for the deployed container */
        public ?bool $service = null,
        /** Path (on the Podman host) to the Kubernetes YAML file to deploy, instead of a request body */
        public ?string $file = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'caCertFile' => $this->caCertFile,
            'kubeConfig' => $this->kubeConfig,
            'namespace' => $this->namespace,
            'service' => $this->service,
            'file' => $this->file,
        ];
    }
}
