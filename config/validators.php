<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Stixx\OpenApiCommandBundle\Validator\OpenApiSpecCache;
use Stixx\OpenApiCommandBundle\Validator\RequestValidator;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorChain;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->private();

    $services
        ->set(RequestValidatorChain::class)
        ->arg('$validators', tagged_iterator(RequestValidatorInterface::TAG_NAME));
    $services->alias(RequestValidatorInterface::class, RequestValidatorChain::class);

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
        ->set(OpenApiSpecCache::class)
            ->arg('$defaultGenerator', service('nelmio_api_doc.generator.default'))
            ->arg('$generatorsLocator', service('stixx_openapi_command.nelmio.generators_locator'))
            ->arg('$router', service('router'))
            ->arg('$configCacheFactory', service('config_cache_factory'))
            ->arg('$buildDir', param('kernel.build_dir'))
            ->arg('$containerFile', '%kernel.build_dir%/%kernel.container_class%.php')
            ->arg('$debug', param('kernel.debug'))
            ->arg('$areaEnv', param('stixx_openapi_command.nelmio.area_env'))
            ->arg('$logger', service('logger')->nullOnInvalid())
            ->arg('$cacheDir', param('kernel.cache_dir'))
            ->tag('kernel.cache_warmer')
            ->tag('monolog.logger', ['channel' => 'stixx_openapi_command']);

    $services
        ->set(RequestValidator::class)
            ->arg('$specCache', service(OpenApiSpecCache::class))
            ->arg('$psrHttpFactory', service('stixx_openapi_command.psr_http_factory'))
            ->arg('$areaRoutesChecker', service(NelmioAreaRoutesChecker::class))
            ->tag(RequestValidatorInterface::TAG_NAME);
};
