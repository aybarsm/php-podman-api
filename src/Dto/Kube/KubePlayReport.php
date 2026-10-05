<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Kube;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Dto\Pod\PodActionReport;
use Aybarsm\Podman\Api\Dto\Pod\PodRemoveReport;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Override;

/**
 * Result of `podman kube play` (pods, volumes, secrets created) and `podman kube down` (stop/remove reports).
 *
 * The single-field wrappers PlayKubeVolume {Name} and PlaySecret {CreateReport: {ID}} are flattened to lists of
 * volume names and secret IDs.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/PlayKubeReport (degraded in v5.8)
 */
final readonly class KubePlayReport implements Hydratable
{
    /**
     * @param list<KubePlayPod>            $pods                pods created
     * @param list<string>                 $volumes             names of the volumes created
     * @param list<string>                 $secrets             IDs of the secrets created
     * @param list<PodActionReport>        $stopReports         pods stopped (kube down)
     * @param list<PodRemoveReport>        $removeReports       pods removed (kube down)
     * @param list<KubeSecretRemoveReport> $secretRemoveReports secrets removed (kube down)
     * @param list<KubeVolumeRemoveReport> $volumeRemoveReports volumes removed (kube down with force)
     */
    public function __construct(
        public array $pods = [],
        public array $volumes = [],
        public array $secrets = [],
        /** ID of the service container, if one was created */
        public ?string $serviceContainerId = null,
        /** Exit code to exit with, if set */
        public ?int $exitCode = null,
        public array $stopReports = [],
        public array $removeReports = [],
        public array $secretRemoveReports = [],
        public array $volumeRemoveReports = [],
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            pods: Data::objectList($data, 'Pods', KubePlayPod::fromArray(...)),
            volumes: Data::objectList(
                $data,
                'Volumes',
                static fn (array $volume): string => Data::string($volume, 'Name'),
            ),
            secrets: Data::objectList(
                $data,
                'Secrets',
                static fn (array $secret): string => Data::string(Data::map($secret, 'CreateReport'), 'ID'),
            ),
            serviceContainerId: Data::stringOrNull($data, 'ServiceContainerID'),
            exitCode: Data::intOrNull($data, 'ExitCode'),
            stopReports: Data::objectList($data, 'StopReport', PodActionReport::fromArray(...)),
            removeReports: Data::objectList($data, 'RmReport', PodRemoveReport::fromArray(...)),
            secretRemoveReports: Data::objectList($data, 'SecretRmReport', KubeSecretRemoveReport::fromArray(...)),
            volumeRemoveReports: Data::objectList($data, 'VolumeRmReport', KubeVolumeRemoveReport::fromArray(...)),
        );
    }
}
