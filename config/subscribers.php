<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\EventSubscriber\ApiExceptionSubscriber;
use Stixx\OpenApiCommandBundle\EventSubscriber\RequestValidatorSubscriber;
use Stixx\OpenApiCommandBundle\Exception\DefaultExceptionToApiProblemTransformer;
use Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire(true)
            ->autoconfigure(false)
            ->private();

    $services
        ->set(DefaultExceptionToApiProblemTransformer::class);

    $services
        ->alias(ExceptionToApiProblemTransformerInterface::class, DefaultExceptionToApiProblemTransformer::class);

    $services
        ->set(ApiExceptionSubscriber::class)
            ->arg('$serializer', service('stixx_openapi_command.problem_serializer'))
            ->tag('kernel.event_subscriber');
    $services
        ->set(RequestValidatorSubscriber::class)
            ->tag('kernel.event_subscriber');
};
