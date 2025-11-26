<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Serializer\Serializer;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ConstraintViolationNormalizer;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ConstraintViolationListNormalizer;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire(true)
            ->autoconfigure(false)
            ->private();

    $services
        ->set(ConstraintViolationNormalizer::class)
            ->tag('serializer.normalizer');
    $services
        ->set(ConstraintViolationListNormalizer::class)
            ->tag('serializer.normalizer');

    $services
        ->set('stixx_openapi_command.problem_serializer', Serializer::class)
            ->arg('$normalizers', [
                service(ConstraintViolationNormalizer::class),
                service(ConstraintViolationListNormalizer::class),
            ])
            ->arg('$encoders', [])
            ->tag('stixx_openapi_command.problem_serializer');
};
