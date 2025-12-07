<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\RouteDescriber\CommandRouteDescriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire()
            ->autoconfigure(false)
            ->private();

    $services
        ->set(CommandRouteDescriber::class)
            ->arg('$argumentMetadataFactory', service('argument_metadata_factory'))
            ->arg('$inlineParameterDescribers', tagged_iterator('nelmio_api_doc.route_argument_describer'))
            ->tag('nelmio_api_doc.route_describer', ['priority' => -260]);
};
