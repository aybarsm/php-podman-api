<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Network\LeaseRange;
use Aybarsm\Podman\Api\Dto\Network\Network;
use Aybarsm\Podman\Api\Dto\Network\NetworkAddress;
use Aybarsm\Podman\Api\Dto\Network\NetworkConnectRequest;
use Aybarsm\Podman\Api\Dto\Network\NetworkCreateRequest;
use Aybarsm\Podman\Api\Dto\Network\Route;
use Aybarsm\Podman\Api\Dto\Network\Subnet;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;

it('lists networks with filters', function (): void {
    $mock = mockPodman()->fixture('networks/list.json');

    $networks = $mock->client()->networks()->list(Filters::of(['driver' => 'bridge']));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/networks/json?filters={"driver":["bridge"]}')
        ->and($networks)->toHaveCount(2)
        ->and($networks[0])->toBeInstanceOf(Network::class);

    [$default, $app] = $networks;

    expect($default->name)->toBe('podman')
        ->and($default->subnets[0]->subnet)->toBe('10.88.0.0/16')
        ->and($default->subnets[0]->leaseRange)->toBeNull()
        ->and($default->routes)->toBe([])
        ->and($default->labels)->toBe([])
        ->and($default->dnsEnabled)->toBeFalse()
        ->and($app->internal)->toBeTrue()
        ->and($app->dnsEnabled)->toBeTrue()
        ->and($app->created?->format('Y-m-d H:i:s.u'))->toBe('2025-06-02 09:30:00.500000')
        ->and($app->subnets[0]->leaseRange?->endIp)->toBe('10.89.0.200')
        ->and($app->routes[0]->metric)->toBe(100)
        ->and($app->options)->toBe(['mtu' => '1500'])
        ->and($app->networkDnsServers)->toBe(['1.1.1.1']);
});

it('inspects a network with its containers', function (): void {
    $mock = mockPodman()->fixture('networks/inspect.json');

    $network = $mock->client()->networks()->inspect('app-net');
    $container = $network->containers['6c5d4b3a2f1e0d9c8b7a6f5e4d3c2b1a6c5d4b3a2f1e0d9c8b7a6f5e4d3c2b1a'] ?? null;

    expect($mock->lastTarget())->toBe('/libpod/networks/app-net/json')
        ->and($network->id)->toStartWith('c0ffee')
        ->and($container?->name)->toBe('web')
        ->and($container?->interfaces['eth0']->macAddress ?? null)->toBe('6a:4f:12:9e:00:01')
        ->and($container?->interfaces['eth0']->subnets[0]->ipnet ?? null)->toBe('10.89.0.2/24')
        ->and($container?->interfaces['eth0']->subnets[0]->gateway ?? null)->toBe('10.89.0.1');
});

it('reads the net.IPNet object form the spec documents for ipnet', function (): void {
    $address = NetworkAddress::fromArray(['ipnet' => ['IP' => '10.89.0.2', 'Mask' => '////AA=='], 'gateway' => '10.89.0.1']);

    expect($address->ipnet)->toBe('10.89.0.2');
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'network not found');
    $networks = $mock->client()->networks();

    expect($networks->exists('app-net'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/networks/app-net/exists')
        ->and($networks->exists('nope'))->toBeFalse();
});

it('creates a network, sending only set fields', function (): void {
    $mock = mockPodman()->fixture('networks/create.json');

    $network = $mock->client()->networks()->create(new NetworkCreateRequest(
        name: 'app-net',
        driver: 'bridge',
        networkInterface: 'podman9',
        subnets: [new Subnet('10.89.0.0/24', '10.89.0.1', new LeaseRange('10.89.0.10', '10.89.0.200'))],
        routes: [new Route('10.10.0.0/16', '10.89.0.254')],
        dnsEnabled: true,
        labels: ['app' => 'web'],
    ), ignoreIfExists: true);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/networks/create?ignoreIfExists=true')
        ->and($mock->lastJsonBody())->toBe([
            'name' => 'app-net',
            'driver' => 'bridge',
            'network_interface' => 'podman9',
            'subnets' => [[
                'subnet' => '10.89.0.0/24',
                'gateway' => '10.89.0.1',
                'lease_range' => ['start_ip' => '10.89.0.10', 'end_ip' => '10.89.0.200'],
            ]],
            'routes' => [['destination' => '10.10.0.0/16', 'gateway' => '10.89.0.254']],
            'dns_enabled' => true,
            'labels' => ['app' => 'web'],
        ])
        ->and($network->name)->toBe('app-net')
        ->and($network->ipamOptions)->toBe(['driver' => 'host-local']);
});

it('surfaces a name conflict on create', function (): void {
    mockPodman()->error(409, 'network name app-net already used')
        ->client()->networks()->create(new NetworkCreateRequest(name: 'app-net'));
})->throws(ConflictException::class, 'already used');

it('removes a network', function (): void {
    $mock = mockPodman()->fixture('networks/remove.json');

    $reports = $mock->client()->networks()->remove('app-net', force: true);

    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/networks/app-net?force=true')
        ->and($reports[0]->name)->toBe('app-net')
        ->and($reports[0]->error)->toBeNull();
});

it('connects a container to a network', function (): void {
    $mock = mockPodman()->queue(new GuzzleHttp\Psr7\Response(200));

    $mock->client()->networks()->connect('app-net', new NetworkConnectRequest(
        container: 'web',
        aliases: ['www'],
        staticIps: ['10.89.0.50'],
    ));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/networks/app-net/connect')
        ->and($mock->lastJsonBody())->toBe(['container' => 'web', 'aliases' => ['www'], 'static_ips' => ['10.89.0.50']]);
});

it('disconnects a container from a network', function (): void {
    $mock = mockPodman()->queue(new GuzzleHttp\Psr7\Response(200));

    $mock->client()->networks()->disconnect('app-net', 'web', force: true);

    expect($mock->lastTarget())->toBe('/libpod/networks/app-net/disconnect')
        ->and($mock->lastJsonBody())->toBe(['Container' => 'web', 'Force' => true]);
});

it('updates the DNS servers of a network', function (): void {
    $mock = mockPodman()->queue(new GuzzleHttp\Psr7\Response(200));

    $mock->client()->networks()->update('app-net', addDnsServers: ['9.9.9.9']);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/networks/app-net/update')
        ->and($mock->lastJsonBody())->toBe(['adddnsservers' => ['9.9.9.9']]);
});

it('prunes unused networks', function (): void {
    $mock = mockPodman()->fixture('networks/prune.json');

    $reports = $mock->client()->networks()->prune(Filters::of(['until' => '24h']));

    expect($mock->lastTarget())->toBe('/libpod/networks/prune?filters={"until":["24h"]}')
        ->and($reports)->toHaveCount(2)
        ->and($reports[0]->name)->toBe('old-net')
        ->and($reports[0]->error)->toBeNull()
        ->and($reports[1]->error)->toBe('unspecified error (not serialised by Podman)');
});

it('URL-encodes network names in the path', function (): void {
    $mock = mockPodman()->fixture('networks/inspect.json');

    $mock->client()->networks()->inspect('a/b');

    expect((string) $mock->lastRequest()->getUri()->getPath())->toEndWith('/libpod/networks/a%2Fb/json');
});

it('throws NotFoundException for unknown networks', function (): void {
    mockPodman()->error(404, 'unable to find network with name or ID nope')->client()->networks()->inspect('nope');
})->throws(NotFoundException::class, 'unable to find network');
