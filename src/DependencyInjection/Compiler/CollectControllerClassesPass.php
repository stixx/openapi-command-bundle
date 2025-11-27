<?php

declare(strict_types=1);

/*
 * This file is part of the StixxOpenApiCommandBundle package.
 *
 * (c) Stixx
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stixx\OpenApiCommandBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CollectControllerClassesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $controllerClasses = [];

        foreach ($container->findTaggedServiceIds('controller.service_arguments') as $serviceId => $tags) {
            $definition = $container->findDefinition($serviceId);
            $class = (string) $definition->getClass();

            if (class_exists($class)) {
                $controllerClasses[$class] = true;
            }
        }

        $container->setParameter('stixx_openapi_command.controller_classes', array_keys($controllerClasses));
    }
}
