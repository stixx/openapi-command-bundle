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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Collects Nelmio ApiDoc per-area RouteCollections into a ServiceLocator
 * so we can quickly determine whether a request targets an API route.
 */
final class NelmioApiDocRoutesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nelmio_api_doc.areas')) {
            return;
        }

        $areas = (array) $container->getParameter('nelmio_api_doc.areas');
        $map = [];

        foreach ($areas as $area) {
            $serviceId = sprintf('nelmio_api_doc.routes.%s', $area);

            if ($container->has($serviceId)) {
                $map[$area] = new Reference($serviceId);
            }
        }

        $container->register('stixx_openapi_command.nelmio.routes_locator', ServiceLocator::class)
            ->addTag('container.service_locator')
            ->setPublic(false)
            ->setArguments([$map]);
    }
}
