<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Internal\Transport;

use Aybarsm\Podman\Api\ClientConfig;
use Aybarsm\Podman\Api\Contracts\RequestBody;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Enums\ParameterGating;
use Aybarsm\Podman\Api\Exceptions\ConnectionException;
use Aybarsm\Podman\Api\Exceptions\RequestException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Query;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The only place that touches PSR-7 requests/responses. Resources call send() and receive a Result or an exception.
 *
 * @internal
 */
final readonly class Transport
{
    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
        private ClientConfig $config,
    ) {}

    public function config(): ClientConfig
    {
        return $this->config;
    }

    /**
     * @param array<string, string>                                    $path        values for {placeholders} in the operation path
     * @param array<string, scalar|list<scalar>|Filters|null>          $query
     * @param RequestBody|array<array-key, mixed>|StreamInterface|string|null $body arrays/RequestBody are sent as JSON
     * @param array<string, string>                                    $headers
     *
     * @throws UnsupportedApiVersionException when the operation is newer than the configured API version
     * @throws ConnectionException            when the HTTP client fails
     * @throws RequestException               on any status other than 2xx / 304
     */
    public function send(
        Operation $operation,
        array $path = [],
        array $query = [],
        RequestBody|array|StreamInterface|string|null $body = null,
        array $headers = [],
        ?string $contentType = null,
    ): Result {
        $this->assertSupported($operation, $query);

        $request = $this->requests
            ->createRequest($operation->method()->value, $this->uri($operation, $path, $query))
            ->withHeader('User-Agent', $this->config->userAgent);

        foreach ([...$this->config->headers, ...$headers] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            [$stream, $type] = $this->body($body);
            $request = $request->withBody($stream)->withHeader('Content-Type', $contentType ?? $type);
        }

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw ConnectionException::fromClientException($operation->value, $e);
        }

        return $this->toResult($operation, $response);
    }

    /**
     * @param array<string, string>                           $path
     * @param array<string, scalar|list<scalar>|Filters|null> $query
     */
    public function uri(Operation $operation, array $path = [], array $query = []): string
    {
        $resolved = preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m): string => rawurlencode(
                $path[$m[1]] ?? throw new InvalidArgumentException("Missing path parameter '{$m[1]}' for {$operation->value}."),
            ),
            $operation->path(),
        );

        $uri = $operation->isVersioned()
            ? $this->config->baseUri.'/v'.$this->config->apiVersion->value.$resolved
            : $this->config->baseUri.$resolved;
        $qs = Query::build($query);

        return $qs === '' ? $uri : $uri.'?'.$qs;
    }

    /**
     * @param array<string, scalar|list<scalar>|Filters|null> $query
     */
    private function assertSupported(Operation $operation, array $query): void
    {
        $configured = $this->config->apiVersion;
        $required = $operation->since();
        if (! $configured->isAtLeast($required)) {
            throw new UnsupportedApiVersionException($operation->value, $required, $configured);
        }

        if ($this->config->parameterGating === ParameterGating::Off) {
            return;
        }
        foreach ($operation->queryParameterSince() as $parameter => $since) {
            $value = $query[$parameter] ?? null;
            $isSet = $value !== null && ! ($value instanceof Filters && $value->isEmpty());
            if ($isSet && ! $configured->isAtLeast($since)) {
                throw new UnsupportedApiVersionException($operation->value, $since, $configured, $parameter);
            }
        }
    }

    /**
     * @param RequestBody|array<array-key, mixed>|StreamInterface|string $body
     *
     * @return array{0: StreamInterface, 1: string}
     *
     * @throws JsonException
     */
    private function body(RequestBody|array|StreamInterface|string $body): array
    {
        return match (true) {
            $body instanceof StreamInterface => [$body, 'application/octet-stream'],
            is_string($body) => [$this->streams->createStream($body), 'text/plain'],
            default => [$this->streams->createStream(self::json($body instanceof RequestBody ? $body->toBody() : $body)), 'application/json'],
        };
    }

    /**
     * JSON-encodes a body without null members. An empty payload is sent as `{}`: Podman bodies are Go structs,
     * which reject `[]`.
     *
     * @param array<array-key, mixed> $body
     *
     * @throws JsonException
     */
    private static function json(array $body): string
    {
        $payload = self::withoutNulls($body);

        return $payload === []
            ? '{}'
            : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function withoutNulls(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === null) {
                continue;
            }
            $out[$k] = is_array($v) ? self::withoutNulls($v) : $v;
        }

        return array_is_list($data) ? array_values($out) : $out;
    }

    private function toResult(Operation $operation, ResponseInterface $response): Result
    {
        $status = $response->getStatusCode();

        if (($status >= 200 && $status < 300) || $status === 304) {
            $headers = [];
            foreach ($response->getHeaders() as $name => $values) {
                $headers[strtolower((string) $name)] = array_values($values);
            }

            return new Result($operation->value, $status, $headers, $response->getBody());
        }

        [$message, $cause] = self::errorModel((string) $response->getBody());

        throw RequestException::forStatus($operation->value, $status, $message ?? ($response->getReasonPhrase() ?: null), $cause);
    }

    /**
     * Extracts ErrorModel {message, cause} from an error body, falling back to the raw text.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private static function errorModel(string $body): array
    {
        $data = json_decode($body, true);
        if (is_array($data)) {
            $message = $data['message'] ?? null;
            $cause = $data['cause'] ?? null;

            // Pod state changes answer 409 with a Pod*Report ({Id, Errs: [...]}) instead of an ErrorModel.
            if (! is_string($message) && is_array($data['Errs'] ?? null)) {
                $errors = array_filter($data['Errs'], is_string(...));
                $message = $errors === [] ? null : implode('; ', $errors);
            }

            return [is_string($message) ? $message : null, is_string($cause) ? $cause : null];
        }

        $text = trim($body);

        return [$text === '' ? null : mb_strimwidth($text, 0, 500, '…'), null];
    }
}
