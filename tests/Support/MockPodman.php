<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Tests\Support;

use Aybarsm\Podman\Api\ClientConfig;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Internal\Transport\Transport;
use Aybarsm\Podman\Api\PodmanClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Guzzle MockHandler wired in as the PSR-18 client, with request history.
 */
final class MockPodman
{
    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    private readonly MockHandler $handler;

    private readonly ClientConfig $config;

    public function __construct(?ClientConfig $config = null)
    {
        $this->handler = new MockHandler();
        $this->config = $config ?? ClientConfig::unixSocket('/tmp/podman-test.sock', ApiVersion::latest());
    }

    public static function withVersion(ApiVersion $version): self
    {
        return new self(ClientConfig::unixSocket('/tmp/podman-test.sock', $version));
    }

    public function queue(ResponseInterface|Throwable ...$responses): self
    {
        foreach ($responses as $response) {
            $this->handler->append($response);
        }

        return $this;
    }

    /**
     * @throws JsonException
     */
    public function json(mixed $body, int $status = 200): self
    {
        return $this->queue(new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)));
    }

    public function fixture(string $name, int $status = 200): self
    {
        return $this->queue(new Response($status, ['Content-Type' => 'application/json'], self::read($name)));
    }

    public function noContent(int $status = 204): self
    {
        return $this->queue(new Response($status));
    }

    public function error(int $status, string $message = 'boom', string $cause = 'cause'): self
    {
        return $this->json(['cause' => $cause, 'message' => $message, 'response' => $status], $status);
    }

    public function client(): PodmanClient
    {
        $factory = new HttpFactory();

        return PodmanClient::create($this->config, $this->guzzle(), $factory, $factory);
    }

    public function transport(): Transport
    {
        $factory = new HttpFactory();

        return new Transport($this->guzzle(), $factory, $factory, $this->config);
    }

    public function lastRequest(): RequestInterface
    {
        $last = $this->history[array_key_last($this->history) ?? throw new RuntimeException('No requests were sent')];

        return $last['request'];
    }

    /**
     * Path + query of the last request, without scheme/host and API version prefix.
     */
    public function lastTarget(): string
    {
        $uri = $this->lastRequest()->getUri();
        $path = preg_replace('#^/v[\d.]+#', '', $uri->getPath()) ?? '';

        return $uri->getQuery() === '' ? $path : $path.'?'.urldecode($uri->getQuery());
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function lastJsonBody(): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function requestCount(): int
    {
        return count($this->history);
    }

    public static function read(string $fixture): string
    {
        $path = dirname(__DIR__).'/Fixtures/responses/'.$fixture;

        return (string) file_get_contents(is_file($path) ? $path : throw new RuntimeException("Missing fixture {$fixture}"));
    }

    private function guzzle(): Client
    {
        $stack = HandlerStack::create($this->handler);
        $stack->push(Middleware::history($this->history));

        return new Client(['handler' => $stack, 'http_errors' => false]);
    }
}
