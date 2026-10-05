# Templates

These are copy-ready shapes. `Widget` stands in for the resource. Keep the structure, and replace names, fields and operations with what `bin/spec show` reports.

## Resource

```php
<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Resources;

use Aybarsm\Podman\Api\Dto\Widget\WidgetCreateRequest;
use Aybarsm\Podman\Api\Dto\Widget\WidgetInspect;
use Aybarsm\Podman\Api\Dto\Widget\WidgetListOptions;
use Aybarsm\Podman\Api\Dto\Shared\RegistryAuth;
use Aybarsm\Podman\Api\Dto\Widget\WidgetPullReport;
use Aybarsm\Podman\Api\Dto\Widget\WidgetSummary;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Internal\Operation;
use Aybarsm\Podman\Api\Internal\Support\Data;
use Psr\Http\Message\StreamInterface;

/**
 * Libpod `widgets` operations.
 */
final readonly class Widgets extends AbstractResource
{
    /**
     * List widgets.
     *
     * @return list<WidgetSummary>
     */
    public function list(?WidgetListOptions $options = null): array
    {
        $result = $this->transport->send(Operation::WidgetList, query: $options?->toQuery() ?? []);

        return Data::listOf($result->jsonList(), WidgetSummary::fromArray(...));
    }

    /**
     * Inspect a widget.
     *
     * @throws NotFoundException
     */
    public function inspect(string $nameOrId): WidgetInspect
    {
        $result = $this->transport->send(Operation::WidgetInspect, ['name' => $nameOrId]);

        return WidgetInspect::fromArray($result->jsonObject());
    }

    public function exists(string $nameOrId): bool
    {
        return $this->probe(Operation::WidgetExists, ['name' => $nameOrId]);
    }

    /**
     * Create a widget; returns its ID.
     *
     * @throws ConflictException when the name is taken
     */
    public function create(WidgetCreateRequest $request): string
    {
        $result = $this->transport->send(Operation::WidgetCreate, body: $request);

        return Data::string($result->jsonObject(), 'Id');
    }

    /**
     * Start a widget.
     *
     * @return bool false when it was already running (HTTP 304)
     *
     * @throws NotFoundException
     */
    public function start(string $nameOrId): bool
    {
        return ! $this->transport->send(Operation::WidgetStart, ['name' => $nameOrId])->isNotModified();
    }

    /**
     * Remove a widget.
     *
     * @throws NotFoundException
     * @throws ConflictException when in use and $force is false
     */
    public function remove(string $nameOrId, bool $force = false): void
    {
        $this->transport->send(Operation::WidgetDelete, ['name' => $nameOrId], ['force' => $force]);
    }

    /**
     * Import a tar archive into a widget (upload: always pass contentType).
     */
    public function import(string $nameOrId, StreamInterface|string $tar): void
    {
        $this->transport->send(Operation::WidgetImport, ['name' => $nameOrId], body: $tar, contentType: 'application/x-tar');
    }

    /**
     * Pull a widget; the body is a progress stream of concatenated JSON documents, the last one is the report.
     */
    public function pull(string $reference, ?RegistryAuth $auth = null): WidgetPullReport
    {
        $documents = $this->transport
            ->send(Operation::WidgetPull, query: ['reference' => $reference], headers: RegistryAuth::headers($auth))
            ->jsonDocuments();

        return WidgetPullReport::fromArray(Data::map(['last' => end($documents) ?: []], 'last'));
    }

    /**
     * Render a widget as YAML (text/plain or text/yaml response).
     */
    public function render(string $nameOrId): string
    {
        return $this->transport->send(Operation::WidgetRender, ['name' => $nameOrId])->text();
    }

    /**
     * Export a widget as a tar archive.
     *
     * @since Podman 5.6
     */
    public function export(string $nameOrId): StreamInterface
    {
        return $this->transport->send(Operation::WidgetExport, ['name' => $nameOrId])->stream();
    }
}
```

Then register it on `PodmanClient`:

```php
    private Widgets $widgets;

    private function __construct(private Transport $transport)
    {
        // …
        $this->widgets = new Widgets($transport);
    }

    public function widgets(): Widgets
    {
        return $this->widgets;
    }
```

## Response DTO

```php
<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Widget;

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;
use Override;

/**
 * A widget as returned by the list endpoint.
 *
 * @see resources/podman/swagger-v5.7.yaml#/definitions/ListWidget (degraded in v5.8)
 */
final readonly class WidgetSummary implements Hydratable
{
    /**
     * @param list<string>          $names
     * @param array<string, string> $labels
     */
    public function __construct(
        public string $id,
        public array $names = [],
        public ?string $image = null,
        public ?DateTimeImmutable $created = null,
        public bool $running = false,
        public array $labels = [],
        public ?WidgetState $state = null,
    ) {}

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self(
            id: Data::string($data, 'Id'),
            names: Data::stringList($data, 'Names'),
            image: Data::stringOrNull($data, 'Image'),
            created: Data::dateTimeOrNull($data, 'Created'),
            running: Data::bool($data, 'Running'),
            labels: Data::stringMap($data, 'Labels'),
            state: Data::objectOrNull($data, 'State', WidgetState::fromArray(...)),
        );
    }
}
```

## Query option object

```php
<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Widget;

use Aybarsm\Podman\Api\Contracts\QueryParameters;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Override;

/**
 * Query parameters for Widgets::list() (WidgetListLibpod).
 */
final readonly class WidgetListOptions implements QueryParameters
{
    public function __construct(
        public ?bool $all = null,
        public ?int $limit = null,
        public ?Filters $filters = null,
        /** @since Podman 5.8 */
        public ?bool $external = null,
    ) {}

    #[Override]
    public function toQuery(): array
    {
        return [
            'all' => $this->all,
            'limit' => $this->limit,
            'filters' => $this->filters,
            'external' => $this->external,
        ];
    }
}
```

## Request body object

```php
<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Widget;

use Aybarsm\Podman\Api\Contracts\RequestBody;
use Override;

/**
 * Body for Widgets::create() (WidgetCreateLibpod, definition WidgetSpec).
 */
final readonly class WidgetCreateRequest implements RequestBody
{
    /**
     * @param array<string, string>|null $labels
     * @param array<string, mixed>       $extra  any other WidgetSpec field, sent verbatim (merged last)
     */
    public function __construct(
        public string $image,
        public ?string $name = null,
        public ?array $labels = null,
        public array $extra = [],
    ) {}

    #[Override]
    public function toBody(): array
    {
        return [
            'image' => $this->image,
            'name' => $this->name,
            'labels' => $this->labels,
            ...$this->extra,
        ];
    }
}
```

## Unit test

```php
<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Widget\WidgetListOptions;
use Aybarsm\Podman\Api\Dto\Widget\WidgetSummary;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;

it('lists widgets', function (): void {
    $mock = mockPodman()->fixture('widgets/list.json');

    $widgets = $mock->client()->widgets()->list(new WidgetListOptions(all: true, filters: Filters::of(['label' => 'a=b'])));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/widgets/json?all=true&filters={"label":["a=b"]}')
        ->and($widgets)->toHaveCount(2)
        ->and($widgets[0])->toBeInstanceOf(WidgetSummary::class)
        ->and($widgets[0]->id)->toBe('3f2a…');
});

it('reports a missing widget as not existing', function (): void {
    $mock = mockPodman()->error(404, 'no such widget');

    expect($mock->client()->widgets()->exists('nope'))->toBeFalse();
});

it('throws NotFoundException when inspecting a missing widget', function (): void {
    mockPodman()->error(404)->client()->widgets()->inspect('nope');
})->throws(NotFoundException::class);

it('gates widget export behind Podman 5.6', function (): void {
    MockPodman::withVersion(ApiVersion::V5_4)->client()->widgets()->export('w');
})->throws(UnsupportedApiVersionException::class);
```

## Integration test

```php
<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Widget\WidgetCreateRequest;

beforeEach(function (): void {
    $this->podman = integrationClient();                 // skips without PODMAN_SOCKET; uses the server's API version
    $this->name = 'podman-api-it-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->widgets()->exists($this->name)) {
        $this->podman->widgets()->remove($this->name, force: true);
    }
});

it('round-trips a widget', function (): void {
    $image = ensureTestImage($this->podman);            // only when an image is needed (pulls with PODMAN_PULL=1)

    $id = $this->podman->widgets()->create(new WidgetCreateRequest(image: $image, name: $this->name));

    expect($this->podman->widgets()->inspect($this->name)->id)->toBe($id);
});
```
