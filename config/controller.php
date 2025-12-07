<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Controller\ArgumentResolver\CommandValueResolver;
use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire()
            ->autoconfigure(false)
            ->private();

    $services
        ->set(CommandController::class)
            ->tag('controller.service_arguments')
            ->arg('$validationEnabled', param('stixx_openapi_command.validation.enabled'))
            ->arg('$validationGroups', param('stixx_openapi_command.validation.groups'));

    $services
        ->set(CommandValueResolver::class)
            ->tag('controller.argument_value_resolver');
};
