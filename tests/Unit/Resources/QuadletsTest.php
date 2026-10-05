<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Quadlet\QuadletRemoveOptions;
use Aybarsm\Podman\Api\Dto\Quadlet\QuadletSummary;
use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;
use Aybarsm\Podman\Api\Exceptions\UnsupportedApiVersionException;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;
use GuzzleHttp\Psr7\Response;

it('lists quadlets', function (): void {
    $mock = mockPodman()->fixture('quadlets/list.json');

    $quadlets = $mock->client()->quadlets()->list(Filters::of(['name' => 'web.container']));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/quadlets/json?filters={"name":["web.container"]}')
        ->and($quadlets)->toHaveCount(2)
        ->and($quadlets[0])->toBeInstanceOf(QuadletSummary::class)
        ->and($quadlets[0]->name)->toBe('web.container')
        ->and($quadlets[0]->unitName)->toBe('web.service')
        ->and($quadlets[0]->status)->toBe('active/running')
        ->and($quadlets[0]->pod)->toBe('app.pod')
        ->and($quadlets[1]->pod)->toBeNull();
});

it('reports existence via 204/404', function (): void {
    $mock = mockPodman()->noContent()->error(404, 'no such quadlet');
    $quadlets = $mock->client()->quadlets();

    expect($quadlets->exists('web.container'))->toBeTrue()
        ->and($mock->lastTarget())->toBe('/libpod/quadlets/web.container/exists')
        ->and($quadlets->exists('nope.container'))->toBeFalse();
});

it('prints a quadlet file', function (): void {
    $file = "# web\n[Container]\nImage=nginx\n";
    $mock = mockPodman()->queue(new Response(200, ['Content-Type' => 'text/plain'], $file));

    expect($mock->client()->quadlets()->print('web.container'))->toBe($file)
        ->and($mock->lastTarget())->toBe('/libpod/quadlets/web.container/file');
});

it('installs quadlets from a tar archive', function (): void {
    $mock = mockPodman()->fixture('quadlets/install.json');

    $report = $mock->client()->quadlets()->install('TARBYTES', replace: true, reloadSystemd: false);

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/quadlets?replace=true&reload-systemd=false')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/x-tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('TARBYTES')
        ->and($report->installedQuadlets)->toBe(['/tmp/upload/web.container' => '/home/user/.config/containers/systemd/web.container'])
        ->and($report->hasErrors())->toBeFalse();
});

it('removes a quadlet', function (): void {
    $mock = mockPodman()->fixture('quadlets/remove.json');

    $report = $mock->client()->quadlets()->remove('web.container', new QuadletRemoveOptions(force: true, reloadSystemd: false));

    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/quadlets/web.container?force=true&reload-systemd=false')
        ->and($report->removed)->toBe(['web.container'])
        ->and($report->errors)->toBe(['db.container' => 'quadlet is running: use force to remove'])
        ->and($report->hasErrors())->toBeTrue();
});

it('removes several or all quadlets', function (): void {
    $mock = mockPodman()->fixture('quadlets/remove.json')->fixture('quadlets/remove.json');
    $quadlets = $mock->client()->quadlets();

    $quadlets->removeMany(['web.container', 'db.container'], options: new QuadletRemoveOptions(ignore: true));
    expect($mock->lastRequest()->getMethod())->toBe('DELETE')
        ->and($mock->lastTarget())->toBe('/libpod/quadlets?quadlets=web.container&quadlets=db.container&ignore=true');

    $quadlets->removeMany(all: true);
    expect($mock->lastTarget())->toBe('/libpod/quadlets?all=true');
});

it('throws NotFoundException for unknown quadlets', function (): void {
    mockPodman()->error(404, 'no such quadlet')->client()->quadlets()->print('nope.container');
})->throws(NotFoundException::class, 'no such quadlet');

it('gates quadlet listing behind Podman 5.7', function (): void {
    MockPodman::withVersion(ApiVersion::V5_4)->client()->quadlets()->list();
})->throws(UnsupportedApiVersionException::class);

it('gates quadlet management behind Podman 5.8', function (): void {
    MockPodman::withVersion(ApiVersion::V5_7)->client()->quadlets()->print('web.container');
})->throws(UnsupportedApiVersionException::class);
