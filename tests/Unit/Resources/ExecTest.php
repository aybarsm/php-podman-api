<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Exec\ExecCreateRequest;
use Aybarsm\Podman\Api\Exceptions\ConflictException;
use Aybarsm\Podman\Api\Exceptions\NotFoundException;

it('creates an exec session and returns its ID', function (): void {
    $mock = mockPodman()->fixture('exec/create.json', 201);

    $id = $mock->client()->exec()->create('web', new ExecCreateRequest(
        cmd: ['sh', '-c', 'echo hello'],
        attachStdout: true,
        attachStderr: true,
        tty: true,
        env: ['A=1'],
        workingDir: '/srv',
    ));

    expect($id)->toBe('9f8e7d6c5b4a39281706f5e4d3c2b1a09f8e7d6c5b4a39281706f5e4d3c2b1a0')
        ->and($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/containers/web/exec')
        ->and($mock->lastJsonBody())->toBe([
            'Cmd' => ['sh', '-c', 'echo hello'],
            'AttachStdout' => true,
            'AttachStderr' => true,
            'Tty' => true,
            'Env' => ['A=1'],
            'WorkingDir' => '/srv',
        ]);
});

it('surfaces a paused container on exec create', function (): void {
    mockPodman()->error(409, 'container is paused')
        ->client()->exec()->create('web', new ExecCreateRequest(cmd: ['true']));
})->throws(ConflictException::class, 'container is paused');

it('inspects an exec session', function (): void {
    $mock = mockPodman()->fixture('exec/inspect.json');

    $exec = $mock->client()->exec()->inspect('9f8e7d6c');

    expect($mock->lastRequest()->getMethod())->toBe('GET')
        ->and($mock->lastTarget())->toBe('/libpod/exec/9f8e7d6c/json')
        ->and($exec->id)->toStartWith('9f8e7d6c')
        ->and($exec->containerId)->toStartWith('6c5d4b3a')
        ->and($exec->running)->toBeTrue()
        ->and($exec->pid)->toBe(4242)
        ->and($exec->exitCode)->toBe(0)
        ->and($exec->openStdout)->toBeTrue()
        ->and($exec->openStdin)->toBeFalse()
        ->and($exec->processConfig?->entrypoint)->toBe('sh')
        ->and($exec->processConfig?->arguments)->toBe(['-c', 'echo hello'])
        ->and($exec->processConfig?->tty)->toBeTrue()
        ->and($exec->processConfig?->user)->toBe('root');
});

it('resizes an exec session TTY', function (): void {
    $mock = mockPodman()->noContent(201)->noContent(201);
    $exec = $mock->client()->exec();

    $exec->resize('9f8e7d6c', 40, 120);
    expect($mock->lastRequest()->getMethod())->toBe('POST')
        ->and($mock->lastTarget())->toBe('/libpod/exec/9f8e7d6c/resize?h=40&w=120');

    $exec->resize('9f8e7d6c', 40, 120, ignoreNotRunning: true);
    expect($mock->lastTarget())->toBe('/libpod/exec/9f8e7d6c/resize?h=40&w=120&running=true');
});

it('throws NotFoundException for unknown exec sessions', function (): void {
    mockPodman()->error(404, 'no such exec session')->client()->exec()->inspect('nope');
})->throws(NotFoundException::class, 'no such exec session');
