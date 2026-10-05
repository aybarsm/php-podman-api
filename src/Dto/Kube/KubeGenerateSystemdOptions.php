<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Kube;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Enums\SystemdRestartPolicy;
use Override;

/**
 * Query parameters for Kube::generateSystemd() (GenerateSystemdLibpod).
 */
final readonly class KubeGenerateSystemdOptions implements QueryParameters
{
    /**
     * @param list<string>|null $wants                  systemd Wants= entries
     * @param list<string>|null $after                  systemd After= entries
     * @param list<string>|null $requires               systemd Requires= entries
     * @param list<string>|null $additionalEnvVariables environment variables ("KEY=value") for the units
     */
    public function __construct(
        /** Use container/pod names instead of IDs */
        public ?bool $useName = null,
        /** Create a new container instead of starting an existing one */
        public ?bool $new = null,
        /** Omit the header with the Podman version and timestamp */
        public ?bool $noHeader = null,
        /** Start timeout in seconds */
        public ?int $startTimeout = null,
        /** Stop timeout in seconds (spec default 10) */
        public ?int $stopTimeout = null,
        /** Spec default on-failure */
        public ?SystemdRestartPolicy $restartPolicy = null,
        /** Unit name prefix for containers (spec default "container") */
        public ?string $containerPrefix = null,
        /** Unit name prefix for pods (spec default "pod") */
        public ?string $podPrefix = null,
        /** Separator between prefix and name/ID (spec default "-") */
        public ?string $separator = null,
        /** Seconds to sleep before restarting the service */
        public ?int $restartSec = null,
        public ?array $wants = null,
        public ?array $after = null,
        public ?array $requires = null,
        public ?array $additionalEnvVariables = null,
        /**
         * Add a template specifier to the unit file names.
         *
         * @since Podman 5.8 (spec)
         */
        public ?bool $templateUnitFile = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'useName' => $this->useName,
            'new' => $this->new,
            'noHeader' => $this->noHeader,
            'startTimeout' => $this->startTimeout,
            'stopTimeout' => $this->stopTimeout,
            'restartPolicy' => $this->restartPolicy?->value,
            'containerPrefix' => $this->containerPrefix,
            'podPrefix' => $this->podPrefix,
            'separator' => $this->separator,
            'restartSec' => $this->restartSec,
            'wants' => $this->wants,
            'after' => $this->after,
            'requires' => $this->requires,
            'additionalEnvVariables' => $this->additionalEnvVariables,
            'templateUnitFile' => $this->templateUnitFile,
        ];
    }
}
