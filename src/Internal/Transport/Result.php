<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Internal\Transport;

use Aybarsm\Podman\Api\Exceptions\HydrationException;
use JsonException;
use Psr\Http\Message\StreamInterface;

/**
 * A successful (non-error) response, decoupled from PSR-7 so resources never see ResponseInterface.
 *
 * @internal
 */
final readonly class Result
{
    /**
     * @param array<string, list<string>> $headers lower-cased header names
     */
    public function __construct(
        public string $operationId,
        public int $status,
        public array $headers,
        private StreamInterface $body,
    ) {}

    public function isNotModified(): bool
    {
        return $this->status === 304;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public function text(): string
    {
        return (string) $this->body;
    }

    public function stream(): StreamInterface
    {
        return $this->body;
    }

    public function json(): mixed
    {
        $text = $this->text();
        if (trim($text) === '') {
            return null;
        }

        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw HydrationException::invalidJson($this->operationId, $e->getMessage());
        }
    }

    /**
     * Decodes a body made of several JSON documents, either newline-delimited or simply concatenated
     * (progress streams of pull/push/load). The whole body is buffered; this is not incremental streaming.
     *
     * @return list<mixed>
     */
    public function jsonDocuments(): array
    {
        $text = $this->text();
        $documents = [];
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }
            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $start ??= $i;
                $depth++;
            } elseif (($char === '}' || $char === ']') && --$depth === 0 && $start !== null) {
                try {
                    $documents[] = json_decode(substr($text, $start, $i - $start + 1), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw HydrationException::invalidJson($this->operationId, $e->getMessage());
                }
                $start = null;
            }
        }

        if ($depth !== 0) {
            throw HydrationException::invalidJson($this->operationId, 'truncated JSON document stream');
        }

        return $documents;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonObject(): array
    {
        $data = $this->json();
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw HydrationException::unexpectedType($this->operationId, 'JSON object', $data);
        }

        $out = [];
        foreach ($data as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * For operations whose spec documents a single object while Podman answers with an array of them (or vice versa):
     * `null` → [], a list as-is, an object → [object].
     *
     * @return list<mixed>
     */
    public function jsonListOrObject(): array
    {
        $data = $this->json();

        return match (true) {
            $data === null => [],
            is_array($data) && array_is_list($data) => $data,
            is_array($data) => [$data],
            default => throw HydrationException::unexpectedType($this->operationId, 'JSON array or object', $data),
        };
    }

    /**
     * A JSON array; `null` (Go nil slice) becomes an empty list.
     *
     * @return list<mixed>
     */
    public function jsonList(): array
    {
        $data = $this->json();

        return match (true) {
            $data === null => [],
            is_array($data) && array_is_list($data) => $data,
            default => throw HydrationException::unexpectedType($this->operationId, 'JSON array', $data),
        };
    }
}
