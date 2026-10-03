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
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
final class CollectNelmioApiDocRoutesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nelmio_api_doc.areas')) {
            throw new LogicException($this->missingNelmioMessage($container));
        }

        /** @var list<string> $areas */
        $areas = (array) $container->getParameter('nelmio_api_doc.areas');
        $routesMap = [];
        $pathPatterns = [];
        $areaConfigs = [];
        $areaEnv = [];

        $generatorsMap = [];

        foreach ($areas as $area) {
            $serviceId = sprintf('nelmio_api_doc.routes.%s', $area);

            if (!$container->has($serviceId)) {
                continue;
            }

            $routesMap[$area] = new Reference($serviceId);
            $pathPatterns[$area] = $this->extractPathPatterns($container, $serviceId);
            $areaConfigs[$area] = $this->areaConfig($container, $serviceId);
            $areaEnv[$area] = $this->documentationEnv($container, $area);

            $generatorId = sprintf('nelmio_api_doc.generator.%s', $area);
            if ($container->has($generatorId)) {
                $generatorsMap[$area] = new Reference($generatorId);
            }
        }

        $container->register('stixx_openapi_command.nelmio.routes_locator', ServiceLocator::class)
            ->addTag('container.service_locator')
            ->setPublic(false)
            ->setArguments([$routesMap]);

        $container->register('stixx_openapi_command.nelmio.generators_locator', ServiceLocator::class)
            ->addTag('container.service_locator')
            ->setPublic(false)
            ->setArguments([$generatorsMap]);

        $container->setParameter('stixx_openapi_command.nelmio.path_patterns', $pathPatterns);
        $container->setParameter('stixx_openapi_command.nelmio.areas_hash', hash('xxh128', serialize($areaConfigs)));
        $container->setParameter('stixx_openapi_command.nelmio.area_env', array_filter($areaEnv));
    }

    /**
     * Without the areas parameter the routes locator is never registered, and config/routing.php fails with
     * an opaque "service does not exist" error. Name the missing half of the setup instead.
     */
    private function missingNelmioMessage(ContainerBuilder $container): string
    {
        $example = "\n\nnelmio_api_doc:\n"
            ."    areas:\n"
            ."        default:\n"
            ."            path_patterns: ['^/api']\n";

        if ($container->hasExtension('nelmio_api_doc')) {
            return 'NelmioApiDocBundle is registered but has no configuration, so it defined no areas. '
                .'StixxOpenApiCommandBundle needs at least one area. Create config/packages/nelmio_api_doc.yaml '
                .'with, for example:'.$example;
        }

        return 'NelmioApiDocBundle is not registered in config/bundles.php (its Flex recipe may have been '
            .'skipped). StixxOpenApiCommandBundle requires it to be both registered and configured. Add '
            .'"Nelmio\ApiDocBundle\NelmioApiDocBundle::class => [\'all\' => true]" to config/bundles.php, then '
            .'create config/packages/nelmio_api_doc.yaml with at least one area, for example:'.$example;
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
        $areaConfig = $this->areaConfig($container, $serviceId);
        if (!is_array($areaConfig) || !isset($areaConfig['path_patterns']) || !is_array($areaConfig['path_patterns'])) {
            return [];
        }

        return array_values(array_filter(
            $areaConfig['path_patterns'],
            static fn ($pattern): bool => is_string($pattern) && $pattern !== '',
        ));
    }

    /**
     * @return array<string, string> env name => runtime placeholder
     */
    private function documentationEnv(ContainerBuilder $container, string $area): array
    {
        $documentation = [];
        foreach (['nelmio_api_doc.describers.config', 'nelmio_api_doc.describers.config.'.$area, 'nelmio_api_doc.describers.security.'.$area] as $serviceId) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            $argument = $container->getDefinition($serviceId)->getArguments()[0] ?? null;
            if (is_array($argument)) {
                unset($argument['servers']);
            }
            $documentation[] = $argument;
        }

        $used = [];
        $container->resolveEnvPlaceholders($documentation, null, $used);
        $names = array_map(strval(...), array_keys((array) $used));
        sort($names);

        $env = [];
        foreach ($names as $name) {
            $env[$name] = '%env(default::'.$name.')%';
        }

        return $env;
    }

    private function areaConfig(ContainerBuilder $container, string $serviceId): mixed
    {
        $factory = $container->getDefinition($serviceId)->getFactory();
        if (!is_array($factory) || !isset($factory[0]) || !$factory[0] instanceof Definition) {
            return null;
        }

        return $factory[0]->getArguments()[2] ?? null;
    }
}
