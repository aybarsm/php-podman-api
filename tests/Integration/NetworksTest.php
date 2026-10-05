<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Network\NetworkCreateRequest;
use Aybarsm\Podman\Api\Dto\Network\Subnet;
use Aybarsm\Podman\Api\Dto\Shared\Filters;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->name = 'podman-api-it-net-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->networks()->exists($this->name)) {
        $this->podman->networks()->remove($this->name, force: true);
    }
});

it('runs the full network lifecycle', function (): void {
    $networks = $this->podman->networks();

    $created = $networks->create(new NetworkCreateRequest(
        name: $this->name,
        subnets: [new Subnet('10.231.'.random_int(0, 255).'.0/24')],
        labels: ['podman-api' => 'integration'],
    ));

    expect($created->name)->toBe($this->name)
        ->and($networks->exists($this->name))->toBeTrue();

    $inspect = $networks->inspect($this->name);
    expect($inspect->id)->toBe($created->id)
        ->and($inspect->labels)->toMatchArray(['podman-api' => 'integration'])
        ->and($inspect->containers)->toBe([]);

    $listed = $networks->list(Filters::of(['label' => 'podman-api=integration']));
    expect(array_map(static fn ($n): string => $n->name, $listed))->toContain($this->name);

    $reports = $networks->remove($this->name);
    expect($reports[0]->name)->toBe($this->name)
        ->and($reports[0]->error)->toBeNull()
        ->and($networks->exists($this->name))->toBeFalse();
});

it('prunes unused networks', function (): void {
    $this->podman->networks()->create(new NetworkCreateRequest(name: $this->name, labels: ['podman-api-prune' => $this->name]));

    $reports = $this->podman->networks()->prune(Filters::of(['label' => 'podman-api-prune='.$this->name]));

    expect(array_map(static fn ($r): string => $r->name, $reports))->toBe([$this->name]);
});
