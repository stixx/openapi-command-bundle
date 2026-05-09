<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Routing\Loader\AttributeDirectoryLoaderDecorator;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->private();

    $services
        ->set(NelmioAreaRoutesChecker::class)
            ->arg('$routesLocator', service('stixx_openapi_command.nelmio.routes_locator'))
            ->arg('$pathPatterns', param('stixx_openapi_command.nelmio.path_patterns'));

    $services
        ->set(CommandRouteClassLoader::class)
        ->arg('$env', param('kernel.environment'))
        ->arg('$controllerClasses', param('stixx_openapi_command.controller_classes'))
        ->tag('routing.loader');

    $services
        ->set(AttributeDirectoryLoaderDecorator::class)
            ->decorate('routing.loader.attribute.directory')
            ->arg('$inner', service('.inner'))
            ->arg('$locator', service('file_locator'))
            ->arg('$commandAttributeLoader', service(CommandRouteClassLoader::class))
            ->arg('$projectDir', param('kernel.project_dir'));
};
