<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire(true)
            ->autoconfigure(false)
            ->private();

    $services
        ->set(CommandController::class)
            ->tag('controller.service_arguments');
};
