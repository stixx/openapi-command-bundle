<?php

declare(strict_types=1);

use Stixx\OpenApiCommandBundle\Controller\ArgumentResolver\CommandValueResolver;
use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Response\StatusResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->private();

    $services
        ->set(CommandController::class)
            ->public()
            ->tag('controller.service_arguments')
            ->arg('$commandBus', service(MessageBusInterface::class))
            ->arg('$validator', service(ValidatorInterface::class))
            ->arg('$statusResolver', service(StatusResolverInterface::class))
            ->arg('$responder', service(ResponderInterface::class))
            ->arg('$validationEnabled', param('stixx_openapi_command.validation.enabled'))
            ->arg('$validationGroups', param('stixx_openapi_command.validation.groups'));

    $services
        ->set(CommandValueResolver::class)
            ->arg('$serializer', service('serializer'))
            ->tag('controller.argument_value_resolver');
};
