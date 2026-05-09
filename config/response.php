<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Responder\JsonResponder;
use Stixx\OpenApiCommandBundle\Responder\JsonSerializedResponder;
use Stixx\OpenApiCommandBundle\Responder\NullableResponder;
use Stixx\OpenApiCommandBundle\Responder\ResponderChain;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Responder\ScalarResponder;
use Stixx\OpenApiCommandBundle\Response\ResponseStatusResolver;
use Stixx\OpenApiCommandBundle\Response\StatusResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->private();

    $services
        ->set(ResponderChain::class)
        ->arg('$responders', tagged_iterator(ResponderInterface::TAG_NAME));
    $services->alias(ResponderInterface::class, ResponderChain::class);

    $services->set(ResponseStatusResolver::class);
    $services->alias(StatusResolverInterface::class, ResponseStatusResolver::class);

    $services->set(JsonResponder::class)
        ->tag(ResponderInterface::TAG_NAME);
    $services->set(JsonSerializedResponder::class)
        ->arg('$serializer', service('serializer'))
        ->tag(ResponderInterface::TAG_NAME);
    $services->set(ScalarResponder::class)
        ->tag(ResponderInterface::TAG_NAME);
    $services->set(NullableResponder::class)
        ->tag(ResponderInterface::TAG_NAME);
};
