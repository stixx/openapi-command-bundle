<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutes;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\AttributeDirectoryLoaderDecorator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire(true)
            ->autoconfigure(false)
            ->private();

    $services
        ->set(NelmioAreaRoutes::class)
            ->arg('$routesLocator', service('stixx_openapi_command.nelmio.routes_locator'));

    $services
        ->set(CommandRouteClassLoader::class)
            ->arg('$controllerClasses', param('stixx_openapi_command.controller_classes'));

    $services
        ->set(AttributeDirectoryLoaderDecorator::class)
            ->decorate('routing.loader.attribute.directory')
            ->arg('$inner', service('.inner'))
            ->arg('$locator', service('file_locator'))
            ->arg('$commandAttributeLoader', service(CommandRouteClassLoader::class))
            ->arg('$projectDir', param('kernel.project_dir'));
};
