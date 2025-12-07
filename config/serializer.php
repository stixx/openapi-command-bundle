<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ApiProblemNormalizer;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ConstraintViolationNormalizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Serializer\Serializer;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire()
            ->autoconfigure(false)
            ->private();

    $services
        ->set(ApiProblemNormalizer::class)
            ->arg('$debug', '%kernel.debug%')
            ->tag('serializer.normalizer');
    $services
        ->set(ConstraintViolationNormalizer::class)
            ->tag('serializer.normalizer');
    $services
        ->set(ConstraintViolationListNormalizer::class)
            ->tag('serializer.normalizer');

    $services
        ->set('stixx_openapi_command.problem_serializer', Serializer::class)
            ->arg('$normalizers', [
                service(ApiProblemNormalizer::class),
                service(ConstraintViolationNormalizer::class),
                service(ConstraintViolationListNormalizer::class),
            ])
            ->arg('$encoders', [])
            ->tag('stixx_openapi_command.problem_serializer');
};
