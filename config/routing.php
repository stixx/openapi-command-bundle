<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutes;
use Stixx\OpenApiCommandBundle\Routing\CommandTaggedRouteLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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
        ->set(CommandTaggedRouteLoader::class)
            ->arg('$taggedRoutes', '%stixx_openapi_command.tagged_routes%')
            ->tag('routing.loader');
};
