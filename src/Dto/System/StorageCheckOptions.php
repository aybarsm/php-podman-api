<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\System;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for System::check() (SystemCheckLibpod).
 */
final readonly class StorageCheckOptions implements QueryParameters
{
    public function __construct(
        /** Skip time-consuming checks */
        public ?bool $quick = null,
        /** Remove inconsistent images */
        public ?bool $repair = null,
        /** Remove inconsistent containers and images */
        public ?bool $repairLossy = null,
        /** Max age of unreferenced layers before removal, Go duration (spec default "24h0m0s") */
        public ?string $unreferencedLayerMaxAge = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'quick' => $this->quick,
            'repair' => $this->repair,
            'repair_lossy' => $this->repairLossy,
            'unreferenced_layer_max_age' => $this->unreferencedLayerMaxAge,
        ];
    }
}
