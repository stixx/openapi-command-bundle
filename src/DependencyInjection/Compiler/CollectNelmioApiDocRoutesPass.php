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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class CollectNelmioApiDocRoutesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nelmio_api_doc.areas')) {
            return;
        }

        /** @var list<string> $areas */
        $areas = (array) $container->getParameter('nelmio_api_doc.areas');
        $routesMap = [];
        $pathPatterns = [];

        foreach ($areas as $area) {
            $serviceId = sprintf('nelmio_api_doc.routes.%s', $area);

            if (!$container->has($serviceId)) {
                continue;
            }

            $routesMap[$area] = new Reference($serviceId);
            $pathPatterns[$area] = $this->extractPathPatterns($container, $serviceId);
        }

        $container->register('stixx_openapi_command.nelmio.routes_locator', ServiceLocator::class)
            ->addTag('container.service_locator')
            ->setPublic(false)
            ->setArguments([$routesMap]);

        $container->setParameter('stixx_openapi_command.nelmio.path_patterns', $pathPatterns);
    }

    /**
     * Reads `path_patterns` from the FilteredRouteCollectionBuilder factory definition that Nelmio
     * registers per area. When the area has no filter config, Nelmio uses the full router collection
     * directly (no factory) — return [] so the checker falls back to "no path-only matching".
     *
     * @return list<string>
     */
    private function extractPathPatterns(ContainerBuilder $container, string $serviceId): array
    {
        $factory = $container->getDefinition($serviceId)->getFactory();
        if (!is_array($factory) || !isset($factory[0]) || !$factory[0] instanceof Definition) {
            return [];
        }

        $arguments = $factory[0]->getArguments();
        $areaConfig = $arguments[2] ?? null;
        if (!is_array($areaConfig) || !isset($areaConfig['path_patterns']) || !is_array($areaConfig['path_patterns'])) {
            return [];
        }

        return array_values(array_filter(
            $areaConfig['path_patterns'],
            static fn ($pattern): bool => is_string($pattern) && $pattern !== '',
        ));
    }
}
