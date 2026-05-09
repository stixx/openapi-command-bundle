<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\EventSubscriber\ApiExceptionSubscriber;
use Stixx\OpenApiCommandBundle\EventSubscriber\RequestValidatorSubscriber;
use Stixx\OpenApiCommandBundle\Exception\DefaultExceptionToApiProblemTransformer;
use Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface;
use Stixx\OpenApiCommandBundle\Exception\WrappedExceptionUnwrapper;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorChain;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->private();

    $services
        ->set(DefaultExceptionToApiProblemTransformer::class);

    $services
        ->alias(ExceptionToApiProblemTransformerInterface::class, DefaultExceptionToApiProblemTransformer::class);

    $services
        ->set(WrappedExceptionUnwrapper::class);

    $services
        ->set(ApiExceptionSubscriber::class)
            ->arg('$nelmioAreaRoutesChecker', service(NelmioAreaRoutesChecker::class))
            ->arg('$normalizer', service('stixx_openapi_command.problem_serializer'))
            ->arg('$exceptionTransformer', service(ExceptionToApiProblemTransformerInterface::class))
            ->arg('$exceptionUnwrapper', service(WrappedExceptionUnwrapper::class))
            ->tag('kernel.event_subscriber');
    $services
        ->set(RequestValidatorSubscriber::class)
            ->arg('$requestValidatorChain', service(RequestValidatorChain::class))
            ->arg('$nelmioAreaRoutes', service(NelmioAreaRoutesChecker::class))
            ->tag('kernel.event_subscriber');
};
