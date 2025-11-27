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

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route as SymfonyRouteAttribute;

final class CommandRouteTaggedPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routes = [];

        foreach ($container->getDefinitions() as $definition) {
            $class = (string) $definition->getClass();
            if (!class_exists($class) || $definition->hasTag('controller.service_arguments')) {
                continue;
            }

            try {
                $ref = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }

            // Only class-level attributes (not method-level), as commands are not controllers
            $attrs = $ref->getAttributes(SymfonyRouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($attrs === []) {
                continue;
            }

            foreach ($attrs as $attr) {
                /** @var SymfonyRouteAttribute $routeAttr */
                $routeAttr = $attr->newInstance();
                $path = (string) $routeAttr->getPath();
                if ($path === '') {
                    continue;
                }

                $routes[] = [
                    'class' => $class,
                    'path' => $path,
                    'methods' => $routeAttr->getMethods(),
                    'name' => $routeAttr->getName(),
                    'requirements' => $routeAttr->getRequirements(),
                    'defaults' => $routeAttr->getDefaults(),
                    'options' => $routeAttr->getOptions(),
                    'host' => $routeAttr->getHost(),
                    'schemes' => $routeAttr->getSchemes(),
                    'condition' => $routeAttr->getCondition(),
                ];
            }
        }

        $container->setParameter('stixx_openapi_command.tagged_routes', $routes);
    }
}
