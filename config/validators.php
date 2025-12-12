<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Validator\RequestValidator;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorChain;
use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface as StixxValidatorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private();

    $services
        ->set(RequestValidator::class)
            ->arg('$apiDocGenerator', service('nelmio_api_doc.generator.default'));

    $services
        ->set(RequestValidatorChain::class)
            ->autoconfigure(false)
            ->arg('$validators', tagged_iterator(StixxValidatorInterface::TAG_NAME));
    $services->alias(StixxValidatorInterface::class, RequestValidatorChain::class);
};
