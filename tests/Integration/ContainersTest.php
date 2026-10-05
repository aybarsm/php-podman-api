<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Container\ContainerCreateSpec;
use Aybarsm\Podman\Api\Dto\Container\ContainerListOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerLogsOptions;
use Aybarsm\Podman\Api\Dto\Container\ContainerRemoveOptions;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Enums\ContainerState;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->name = 'podman-api-it-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->containers()->exists($this->name)) {
        $this->podman->containers()->remove($this->name, new ContainerRemoveOptions(force: true, timeout: 0));
    }
});

it('runs the full container lifecycle', function (): void {
    ensureTestImage($this->podman);

    $containers = $this->podman->containers();

    $created = $containers->create(new ContainerCreateSpec(
        image: PODMAN_TEST_IMAGE,
        name: $this->name,
        command: ['sh', '-c', 'echo hello; echo oops >&2; sleep 300'],
        labels: ['podman-api' => 'integration'],
    ));

    expect($containers->exists($this->name))->toBeTrue()
        ->and($containers->start($this->name))->toBeTrue()
        ->and($containers->start($this->name))->toBeFalse();

    $inspect = $containers->inspect($this->name);
    expect($inspect->id)->toBe($created->id)
        ->and($inspect->state->knownState())->toBe(ContainerState::Running)
        ->and($inspect->labels())->toMatchArray(['podman-api' => 'integration']);

    $listed = $containers->list(new ContainerListOptions(filters: Filters::of(['label' => 'podman-api=integration'])));
    expect(array_map(static fn ($c): string => $c->id, $listed))->toContain($created->id);

    usleep(500_000);
    $logs = $containers->logs($this->name, new ContainerLogsOptions(timestamps: true));
    expect(array_map(static fn ($l): string => $l->stream->value.':'.$l->text, $logs))
        ->toContain('stdout:hello', 'stderr:oops')
        ->and($logs[0]->timestamp)->not->toBeNull();

    $stats = $containers->statsAll([$this->name]);
    expect($stats)->toHaveCount(1)
        ->and($stats[0]->containerId)->toBe($created->id)
        ->and($stats[0]->memUsage)->toBeInt();

    expect($containers->top($this->name)->processes)->not->toBeEmpty()
        ->and($containers->stop($this->name, timeout: 0))->toBeTrue()
        ->and($containers->wait($this->name))->toBeInt();

    $reports = $containers->remove($this->name);
    expect($containers->exists($this->name))->toBeFalse()
        ->and($reports[0]->id ?? $created->id)->toBe($created->id);
});
