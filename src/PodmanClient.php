<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api;

use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Internal\Transport\GuzzleFactory;
use Aybarsm\Podman\Api\Internal\Transport\Transport;
use Aybarsm\Podman\Api\Resources\Artifacts;
use Aybarsm\Podman\Api\Resources\Containers;
use Aybarsm\Podman\Api\Resources\Exec;
use Aybarsm\Podman\Api\Resources\Images;
use Aybarsm\Podman\Api\Resources\Kube;
use Aybarsm\Podman\Api\Resources\Manifests;
use Aybarsm\Podman\Api\Resources\Networks;
use Aybarsm\Podman\Api\Resources\Pods;
use Aybarsm\Podman\Api\Resources\Quadlets;
use Aybarsm\Podman\Api\Resources\Secrets;
use Aybarsm\Podman\Api\Resources\System;
use Aybarsm\Podman\Api\Resources\Volumes;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point. Build one with a factory, then use the resource accessors:
 *
 *     $podman = PodmanClient::unixSocket('/run/user/1000/podman/podman.sock');
 *     $podman->containers()->list();
 */
final readonly class PodmanClient
{
    private Artifacts $artifacts;

    private Containers $containers;

    private Exec $exec;

    private Images $images;

    private Kube $kube;

    private Manifests $manifests;

    private Networks $networks;

    private Pods $pods;

    private Quadlets $quadlets;

    private Secrets $secrets;

    private System $system;

    private Volumes $volumes;

    private function __construct(private Transport $transport)
    {
        $this->artifacts = new Artifacts($transport);
        $this->containers = new Containers($transport);
        $this->exec = new Exec($transport);
        $this->images = new Images($transport);
        $this->kube = new Kube($transport);
        $this->manifests = new Manifests($transport);
        $this->networks = new Networks($transport);
        $this->pods = new Pods($transport);
        $this->quadlets = new Quadlets($transport);
        $this->secrets = new Secrets($transport);
        $this->system = new System($transport);
        $this->volumes = new Volumes($transport);
    }

    /**
     * Any PSR-18 client and PSR-17 factories may be injected; defaults to Guzzle configured from $config.
     */
    public static function create(
        ClientConfig $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $factory = new HttpFactory();

        return new self(new Transport(
            $httpClient ?? GuzzleFactory::create($config),
            $requestFactory ?? $factory,
            $streamFactory ?? $factory,
            $config,
        ));
    }

    public static function unixSocket(string $socketPath = ClientConfig::DEFAULT_SOCKET, ?ApiVersion $apiVersion = null): self
    {
        return self::create(ClientConfig::unixSocket($socketPath, $apiVersion));
    }

    public static function tcp(string $baseUri, ?ApiVersion $apiVersion = null): self
    {
        return self::create(ClientConfig::tcp($baseUri, $apiVersion));
    }

    public function config(): ClientConfig
    {
        return $this->transport->config();
    }

    public function artifacts(): Artifacts
    {
        return $this->artifacts;
    }

    public function containers(): Containers
    {
        return $this->containers;
    }

    public function exec(): Exec
    {
        return $this->exec;
    }

    public function images(): Images
    {
        return $this->images;
    }

    public function kube(): Kube
    {
        return $this->kube;
    }

    public function manifests(): Manifests
    {
        return $this->manifests;
    }

    public function networks(): Networks
    {
        return $this->networks;
    }

    public function pods(): Pods
    {
        return $this->pods;
    }

    public function quadlets(): Quadlets
    {
        return $this->quadlets;
    }

    public function secrets(): Secrets
    {
        return $this->secrets;
    }

    public function system(): System
    {
        return $this->system;
    }

    public function volumes(): Volumes
    {
        return $this->volumes;
    }
}
