<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Kube\KubeApplyOptions;
use Aybarsm\Podman\Api\Dto\Kube\KubeGenerateOptions;
use Aybarsm\Podman\Api\Dto\Kube\KubeGenerateSystemdOptions;
use Aybarsm\Podman\Api\Dto\Kube\KubePlayOptions;
use Aybarsm\Podman\Api\Dto\Kube\KubePlayReport;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;
use JsonException;
use Psr\Http\Message\StreamInterface;

/**
 * Kubernetes YAML and systemd unit generation, `kube play` and `kube apply`. `kube down` is deferred: the spec
 * documents no request body for it.
 *
 * The spec tags these operations `containers, pods`; they are grouped here like the `podman kube` CLI.
 */
final readonly class Kube extends AbstractResource
{
    /** PlayKubeLibpod Content-Type for a YAML body (the spec's literal enum value) */
    private const string YAML = 'plain/text';

    /** PlayKubeLibpod Content-Type for a tar archive holding play.yaml plus build contexts */
    private const string TAR = 'application/x-tar';

    /**
     * Generate Kubernetes YAML for containers or pods (`podman kube generate`).
     *
     * @param list<string> $namesOrIds containers and/or pods
     */
    public function generate(array $namesOrIds, ?KubeGenerateOptions $options = null): string
    {
        return $this->transport->send(
            Operation::GenerateKube,
            query: ['names' => $namesOrIds, ...($options?->toQuery() ?? [])],
        )->text();
    }

    /**
     * Generate systemd units for a container or pod (`podman generate systemd`).
     *
     * @return array<string, string> unit name → unit file content
     */
    public function generateSystemd(string $nameOrId, ?KubeGenerateSystemdOptions $options = null): array
    {
        $units = $this->transport
            ->send(Operation::GenerateSystemd, ['name' => $nameOrId], $options?->toQuery() ?? [])
            ->json();

        return Data::stringMap(['units' => $units], 'units');
    }

    /**
     * Create and run pods, containers, volumes and secrets from Kubernetes YAML (`podman kube play`).
     *
     * @param StreamInterface|string $content YAML, or (with $tar) a tar archive with play.yaml at its root plus build
     *                                        contexts (combine with KubePlayOptions::$build)
     *
     * @throws JsonException
     */
    public function play(StreamInterface|string $content, ?KubePlayOptions $options = null, bool $tar = false): KubePlayReport
    {
        $result = $this->transport->send(
            Operation::PlayKube,
            query: $options?->toQuery() ?? [],
            body: $content,
            contentType: $tar ? self::TAR : self::YAML,
        );

        return KubePlayReport::fromArray($result->jsonObject());
    }

    /**
     * Deploy Kubernetes YAML to a Kubernetes cluster (`podman kube apply`).
     *
     * @param StreamInterface|string|null $yaml the YAML to deploy; null when KubeApplyOptions::$file names a file
     *
     * @return string Podman's response text
     */
    public function apply(StreamInterface|string|null $yaml = null, ?KubeApplyOptions $options = null): string
    {
        return $this->transport->send(
            Operation::KubeApply,
            query: $options?->toQuery() ?? [],
            body: $yaml,
        )->text();
    }
}
