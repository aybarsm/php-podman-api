<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Override;

/**
 * Query parameters for Containers::logs() (ContainerLogsLibpod). Follow mode is not supported: logs() returns the
 * output available when the request is made.
 */
final readonly class ContainerLogsOptions implements QueryParameters
{
    public function __construct(
        /** At least one of stdout/stderr must be true (Podman answers 400 otherwise) */
        public bool $stdout = true,
        public bool $stderr = true,
        /** Prefix each line with its RFC 3339 timestamp; parsed into LogLine::$timestamp */
        public bool $timestamps = false,
        /** Number of lines from the end, or "all" (spec default) */
        public int|string|null $tail = null,
        /** Unix timestamp, RFC 3339 date or Go duration ("10m") */
        public ?string $since = null,
        public ?string $until = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'stdout' => $this->stdout ?: null,
            'stderr' => $this->stderr ?: null,
            'timestamps' => $this->timestamps ?: null,
            'tail' => $this->tail === null ? null : (string) $this->tail,
            'since' => $this->since,
            'until' => $this->until,
            'follow' => false,
        ];
    }
}
