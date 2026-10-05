<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Exceptions\PodmanApiException;
use Aybarsm\Podman\Api\Exceptions\RequestException;
use Aybarsm\Podman\Api\Resources\AbstractResource;

arch('every class declares strict types')
    ->expect('Aybarsm\Podman\Api')
    ->toUseStrictTypes();

arch('no debugging or unsafe php functions')
    ->preset()->php();

arch('response DTOs and option objects are final readonly')
    ->expect('Aybarsm\Podman\Api\Dto')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('resources are readonly')
    ->expect('Aybarsm\Podman\Api\Resources')
    ->classes()
    ->toBeReadonly();

arch('resources are final')
    ->expect('Aybarsm\Podman\Api\Resources')
    ->classes()
    ->toBeFinal()
    ->ignoring(AbstractResource::class);

arch('resources extend AbstractResource')
    ->expect('Aybarsm\Podman\Api\Resources')
    ->classes()
    ->toExtend(AbstractResource::class)
    ->ignoring(AbstractResource::class);

arch('client and config are final readonly')
    ->expect(['Aybarsm\Podman\Api\PodmanClient', 'Aybarsm\Podman\Api\ClientConfig'])
    ->toBeFinal()
    ->toBeReadonly();

arch('public enums are backed')
    ->expect('Aybarsm\Podman\Api\Enums')
    ->toBeEnums()
    ->toBeStringBackedEnums();

arch('every exception extends PodmanApiException')
    ->expect('Aybarsm\Podman\Api\Exceptions')
    ->classes()
    ->toExtend(PodmanApiException::class)
    ->ignoring(PodmanApiException::class);

arch('concrete exceptions are final')
    ->expect('Aybarsm\Podman\Api\Exceptions')
    ->classes()
    ->toBeFinal()
    ->ignoring([PodmanApiException::class, RequestException::class]);

arch('ResponseInterface never leaves the transport')
    ->expect('Psr\Http\Message\ResponseInterface')
    ->toOnlyBeUsedIn('Aybarsm\Podman\Api\Internal\Transport');

arch('Guzzle is only referenced by the client factory and transport')
    ->expect('GuzzleHttp')
    ->toOnlyBeUsedIn(['Aybarsm\Podman\Api\PodmanClient', 'Aybarsm\Podman\Api\Internal\Transport', 'Aybarsm\Podman\Api\Tests']);

arch('spec tooling stays out of the runtime package')
    ->expect(['Aybarsm\Podman\Api\Dev', 'Symfony\Component\Yaml'])
    ->toOnlyBeUsedIn(['Aybarsm\Podman\Api\Dev', 'Aybarsm\Podman\Api\Tests']);
