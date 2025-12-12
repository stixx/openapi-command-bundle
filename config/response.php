<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Responder\ResponderChain;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Response\ResponseStatusResolver;
use Stixx\OpenApiCommandBundle\Response\StatusResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire()
            ->autoconfigure(false)
            ->private();

    $services->set(ResponseStatusResolver::class);
    $services->alias(StatusResolverInterface::class, ResponseStatusResolver::class);

    $services->set(ResponderChain::class);
    $services->alias(ResponderInterface::class, ResponderChain::class);

    $services
        ->set(ResponderChain::class)
        ->arg('$responders', tagged_iterator(ResponderInterface::TAG_NAME));
};
