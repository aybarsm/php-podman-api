<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Kube\KubeApplyOptions;
use Aybarsm\Podman\Api\Dto\Kube\KubeGenerateOptions;
use Aybarsm\Podman\Api\Dto\Kube\KubeGenerateSystemdOptions;
use Aybarsm\Podman\Api\Dto\Kube\KubePlayOptions;
use Aybarsm\Podman\Api\Enums\SystemdRestartPolicy;
use Aybarsm\Podman\Api\Exceptions\ServerException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

const KUBE_YAML = "apiVersion: v1\nkind: Pod\nmetadata:\n  name: web\n";

it('generates Kubernetes YAML', function (): void {
    $mock = mockPodman()->queue(new Response(200, ['Content-Type' => 'text/vnd.yaml'], KUBE_YAML));

    $yaml = $mock->client()->kube()->generate(['web', 'db'], new KubeGenerateOptions(service: true, type: 'deployment', replicas: 2));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/generate/kube?names=web&names=db&service=true&type=deployment&replicas=2')
        ->and($yaml)->toBe(KUBE_YAML);
});

it('generates systemd units', function (): void {
    $mock = mockPodman()->fixture('kube/systemd.json');

    $units = $mock->client()->kube()->generateSystemd('web', new KubeGenerateSystemdOptions(
        useName: true,
        new: true,
        restartPolicy: SystemdRestartPolicy::Always,
        after: ['network-online.target', 'local-fs.target'],
    ));

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe(
            '/libpod/generate/web/systemd?useName=true&new=true&restartPolicy=always&after=network-online.target&after=local-fs.target',
        )
        ->and(array_keys($units))->toBe(['pod-web', 'container-web-app'])
        ->and($units['pod-web'])->toStartWith('# pod-web.service');
});

it('plays Kubernetes YAML', function (): void {
    $mock = mockPodman()->fixture('kube/play.json');

    $report = $mock->client()->kube()->play(KUBE_YAML, new KubePlayOptions(
        annotations: ['io.podman/x' => 'y'],
        network: ['app-net'],
        replace: true,
        start: false,
    ));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/play/kube?annotations={"io.podman/x":"y"}&network=app-net&replace=true&start=false')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('text/plain')
        ->and((string) $mock->lastRequest()->getBody())->toBe(KUBE_YAML)
        ->and($report->pods)->toHaveCount(1)
        ->and($report->pods[0]->id)->toStartWith('9f8e7d6c')
        ->and($report->pods[0]->containers)->toHaveCount(1)
        ->and($report->pods[0]->logs)->toBe([])
        ->and($report->volumes)->toBe(['data'])
        ->and($report->secrets)->toBe(['5c1b0e3a9d8f7e6d5c4b3a291'])
        ->and($report->serviceContainerId)->toBe('')
        ->and($report->exitCode)->toBeNull()
        ->and($report->stopReports)->toBe([]);
});

it('plays a tar archive with build contexts', function (): void {
    $mock = mockPodman()->fixture('kube/play.json');

    $mock->client()->kube()->play(Utils::streamFor('TARBYTES'), new KubePlayOptions(build: true), tar: true);

    expect($mock->lastTarget())->toBe('/libpod/play/kube?build=true')
        ->and($mock->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/x-tar')
        ->and((string) $mock->lastRequest()->getBody())->toBe('TARBYTES');
});

it('applies Kubernetes YAML to a cluster', function (): void {
    $mock = mockPodman()->queue(new Response(200, [], 'Deployed!'));

    $out = $mock->client()->kube()->apply(KUBE_YAML, new KubeApplyOptions(kubeConfig: '/root/.kube/config', namespace: 'dev'));

    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/kube/apply?kubeConfig=/root/.kube/config&namespace=dev')
        ->and((string) $mock->lastRequest()->getBody())->toBe(KUBE_YAML)
        ->and($out)->toBe('Deployed!');
});

it('applies a YAML file on the Podman host without a body', function (): void {
    $mock = mockPodman()->queue(new Response(200, [], ''));

    $mock->client()->kube()->apply(options: new KubeApplyOptions(file: '/srv/app.yaml'));

    expect($mock->lastTarget())->toBe('/libpod/kube/apply?file=/srv/app.yaml')
        ->and((string) $mock->lastRequest()->getBody())->toBe('')
        ->and($mock->lastRequest()->hasHeader('Content-Type'))->toBeFalse();
});

it('surfaces play failures as server errors', function (): void {
    mockPodman()->error(500, 'unable to read YAML')->client()->kube()->play('not: [yaml');
})->throws(ServerException::class, 'unable to read YAML');
