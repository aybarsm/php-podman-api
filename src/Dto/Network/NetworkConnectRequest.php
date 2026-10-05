<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Network;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for Networks::connect() (NetworkConnectLibpod).
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/NetworkConnectOptions (degraded in v5.8; referenced through
 *      networkConnectRequestLibpod)
 */
final readonly class NetworkConnectRequest implements RequestBody
{
    /**
     * @param list<string>|null          $aliases   names the network's DNS server resolves to this container
     * @param list<string>|null          $staticIps
     * @param array<string, string>|null $options   driver-specific options
     */
    public function __construct(
        /** Container name or ID */
        public string $container,
        public ?array $aliases = null,
        public ?string $interfaceName = null,
        public ?array $staticIps = null,
        public ?string $staticMac = null,
        public ?array $options = null,
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'container' => $this->container,
            'aliases' => $this->aliases,
            'interface_name' => $this->interfaceName,
            'static_ips' => $this->staticIps,
            'static_mac' => $this->staticMac,
            'options' => $this->options,
        ];
    }
}
