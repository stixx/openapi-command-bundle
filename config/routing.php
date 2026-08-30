<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Routing\CommandRouteDiscovery;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\RouterLoaderDecorator;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Stixx\OpenApiCommandBundle\Routing\RouteSpecificitySorter;
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
        ->set(RouteSpecificitySorter::class);

    $services
        ->set(CommandRouteClassLoader::class)
            ->arg('$env', param('kernel.environment'))
            ->arg('$controllerClasses', param('stixx_openapi_command.controller_classes'))
            // Keeps `$routes->import(SomeCommand::class, 'attribute')` working for applications that prefer
            // to declare command routes explicitly instead of relying on discovery.
            ->tag('routing.loader');

    $services
        ->set(CommandRouteDirectoryLoader::class)
            ->arg('$locator', service('file_locator'))
            ->arg('$loader', service(CommandRouteClassLoader::class))
            // Allows `$routes->import('../src/Command', 'stixx_openapi_command.command_attributes')` for
            // applications that want to control which directories are scanned from their routing config.
            ->tag('routing.loader');

    $services
        ->set(CommandRouteDiscovery::class)
            ->arg('$directoryLoader', service(CommandRouteDirectoryLoader::class))
            ->arg('$commandPaths', param('stixx_openapi_command.command_paths'))
            ->arg('$sorter', service(RouteSpecificitySorter::class));

    // Decorates `routing.loader` (the DelegatingLoader the router asks for) rather than one of the loaders
    // behind it, so command routes are added regardless of how the application declares its own routes.
    $services
        ->set(RouterLoaderDecorator::class)
            ->decorate('routing.loader')
            ->arg('$inner', service('.inner'))
            ->arg('$discovery', service(CommandRouteDiscovery::class));
};
