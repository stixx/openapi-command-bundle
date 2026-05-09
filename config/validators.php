<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Stixx\OpenApiCommandBundle\Validator\RequestValidator;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorChain;
use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface as StixxValidatorInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->private();

    $services
        ->set(RequestValidatorChain::class)
        ->arg('$validators', tagged_iterator(StixxValidatorInterface::TAG_NAME));
    $services->alias(StixxValidatorInterface::class, RequestValidatorChain::class);

    $services
        ->set('stixx_openapi_command.psr17_factory', Psr17Factory::class);

    $services
        ->set('stixx_openapi_command.psr_http_factory', PsrHttpFactory::class)
        ->args([
            service('stixx_openapi_command.psr17_factory'),
            service('stixx_openapi_command.psr17_factory'),
            service('stixx_openapi_command.psr17_factory'),
            service('stixx_openapi_command.psr17_factory'),
        ]);

    $services
        ->set(RequestValidator::class)
            ->arg('$apiDocGenerator', service('nelmio_api_doc.generator.default'))
            ->arg('$psrHttpFactory', service('stixx_openapi_command.psr_http_factory'))
            ->tag(StixxValidatorInterface::TAG_NAME);
};
