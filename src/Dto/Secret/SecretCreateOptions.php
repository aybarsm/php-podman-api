<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Secret;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Internal\Support\Query;
use JsonException;
use Override;

/**
 * Query parameters for Secrets::create() (SecretCreateLibpod), excluding the secret name.
 */
final readonly class SecretCreateOptions implements QueryParameters
{
    /**
     * @param array<string, string>|null $driverOptions sent as the JSON-encoded `driveropts` parameter
     * @param array<string, string>|null $labels        sent as the JSON-encoded `labels` parameter
     */
    public function __construct(
        /** Secret driver (spec default "file") */
        public ?string $driver = null,
        public ?array $driverOptions = null,
        public ?array $labels = null,
        /**
         * Replace an existing secret with the same name.
         *
         * @since Podman 5.8 (spec)
         */
        public ?bool $replace = null,
        /**
         * Ignore the request if a secret with the same name already exists.
         *
         * @since Podman 5.8 (spec)
         */
        public ?bool $ignore = null,
    ) {}

    /**
     * @throws JsonException
     */
    #[Override]
    public function toQuery(): array
    {
        return [
            'driver' => $this->driver,
            'driveropts' => Query::json($this->driverOptions),
            'labels' => Query::json($this->labels),
            'replace' => $this->replace,
            'ignore' => $this->ignore,
        ];
    }
}
