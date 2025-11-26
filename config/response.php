<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Response\ResponseStatusResolver;
use Stixx\OpenApiCommandBundle\Response\StatusResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire(true)
            ->autoconfigure(false)
            ->private();

    $services->set(ResponseStatusResolver::class);
    $services->alias(StatusResolverInterface::class, ResponseStatusResolver::class);
};
