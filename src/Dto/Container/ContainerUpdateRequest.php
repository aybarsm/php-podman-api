<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for Containers::update() (ContainerUpdateLibpod).
 *
 * Resource sections are raw OCI structures; other UpdateEntities fields (health_* tuning, device throttles, …)
 * go through $extra by JSON name (see `bin/spec show UpdateEntities`).
 *
 * @see resources/podman/swagger-v5.8.yaml#/definitions/UpdateEntities
 */
final readonly class ContainerUpdateRequest implements RequestBody
{
    /**
     * @param list<string>|null         $env      "KEY=value" entries to add or replace
     * @param list<string>|null         $unsetEnv variable names to remove
     * @param array<string, mixed>|null $cpu      LinuxCPU: shares, quota, period, cpus, mems, …
     * @param array<string, mixed>|null $memory   LinuxMemory: limit, reservation, swap, …
     * @param array<string, mixed>|null $pids     LinuxPids: limit
     * @param array<string, mixed>      $extra    any other UpdateEntities field, merged last
     */
    public function __construct(
        public ?array $env = null,
        public ?array $unsetEnv = null,
        public ?array $cpu = null,
        public ?array $memory = null,
        public ?array $pids = null,
        public ?string $healthCmd = null,
        public ?string $healthInterval = null,
        public ?bool $noHealthcheck = null,
        public array $extra = [],
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'Env' => $this->env,
            'UnsetEnv' => $this->unsetEnv,
            'cpu' => $this->cpu,
            'memory' => $this->memory,
            'pids' => $this->pids,
            'health_cmd' => $this->healthCmd,
            'health_interval' => $this->healthInterval,
            'no_healthcheck' => $this->noHealthcheck,
            ...$this->extra,
        ];
    }
}
