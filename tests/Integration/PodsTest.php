<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Pod\PodCreateSpec;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Enums\PodStatus;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->name = 'podman-api-it-pod-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->pods()->exists($this->name)) {
        $this->podman->pods()->remove($this->name, force: true);
    }
});

it('runs the full pod lifecycle', function (): void {
    $pods = $this->podman->pods();

    $id = $pods->create(new PodCreateSpec(name: $this->name, labels: ['podman-api' => 'integration']));

    expect($pods->exists($this->name))->toBeTrue();

    $inspect = $pods->inspect($this->name);
    expect($inspect->id)->toBe($id)
        ->and($inspect->name)->toBe($this->name)
        ->and($inspect->labels)->toMatchArray(['podman-api' => 'integration']);

    $listed = $pods->list(Filters::of(['label' => 'podman-api=integration']));
    expect(array_map(static fn ($p): string => $p->id, $listed))->toContain($id);

    expect($pods->start($this->name))->toBeTrue()
        ->and($pods->inspect($this->name)->knownState())->toBe(PodStatus::Running)
        ->and($pods->top($this->name)->titles)->not->toBeEmpty()
        ->and($pods->stats([$this->name]))->toBeArray();

    $pods->pause($this->name);
    $pods->unpause($this->name);
    $pods->restart($this->name);

    expect($pods->stop($this->name, timeout: 0))->toBeTrue();

    $report = $pods->remove($this->name);
    expect($report->id)->toBe($id)
        ->and($report->error)->toBeNull()
        ->and($pods->exists($this->name))->toBeFalse();
});

it('prunes stopped pods', function (): void {
    $pods = $this->podman->pods();
    $id = $pods->create(new PodCreateSpec(name: $this->name));
    $pods->start($this->name);
    $pods->stop($this->name, timeout: 0);

    $ids = array_map(static fn ($r): string => $r->id, $pods->prune());

    expect($ids)->toContain($id)
        ->and($pods->exists($this->name))->toBeFalse();
});
