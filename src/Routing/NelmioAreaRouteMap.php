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

namespace Stixx\OpenApiCommandBundle\Routing;

use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Which Nelmio area each route belongs to, built from Nelmio's own filtered route collections and cached
 * like the router's matcher, so a request never has to load the routes to find its area.
 *
 * @internal
 */
final class NelmioAreaRouteMap implements CacheWarmerInterface
{
    /**
     * @var array<string, string>|null
     */
    private ?array $areas = null;

    /**
     * @param ServiceLocator<RouteCollection> $routesLocator
     */
    public function __construct(
        private readonly ServiceLocator $routesLocator,
        private readonly ?RouterInterface $router = null,
        private readonly ?ConfigCacheFactoryInterface $configCacheFactory = null,
        private readonly ?string $buildDir = null,
        private readonly string $areasHash = '',
        private readonly ?string $cacheDir = null,
        private readonly ?string $buildId = null,
    ) {
    }

    public function areaOf(string $routeName): ?string
    {
        $this->areas ??= $this->load($this->runtimeDirs());

        return $this->areas[$routeName] ?? null;
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($buildDir === null) {
            return [];
        }

        $this->areas = $this->load([$buildDir.'/stixx_openapi_command']);

        return [];
    }

    /**
     * @param list<string|null> $dirs where to cache the map, in order
     *
     * @return array<string, string>
     */
    private function load(array $dirs): array
    {
        if ($this->router === null || $this->configCacheFactory === null) {
            return $this->build();
        }

        $dirs = array_unique(array_filter($dirs, static fn (?string $dir): bool => $dir !== null && $dir !== ''));
        $file = '/nelmio_area_routes.'.$this->areasHash.'.php';
        foreach ($dirs as $dir) {
            $stale = false;
            $cache = $this->configCacheFactory->cache($dir.$file, static function () use (&$stale): void {
                $stale = true;
            });

            if (!$stale && is_file($cache->getPath())) {
                /** @var array<string, string> $areas */
                $areas = require $cache->getPath();

                return $areas;
            }
        }

        $areas = $this->build();
        $resources = $this->router->getRouteCollection()->getResources();
        foreach ($dirs as $dir) {
            try {
                $this->configCacheFactory->cache($dir.$file, static function (ConfigCacheInterface $cache) use ($areas, $resources): void {
                    $cache->write('<?php return '.var_export($areas, true).";\n", $resources);
                });

                break;
            } catch (IOException) {
                continue;
            }
        }

        return $areas;
    }

    /**
     * The cache dir outlives a deploy, so its copy is scoped to the container build, as Symfony's system cache is.
     *
     * @return list<string|null>
     */
    private function runtimeDirs(): array
    {
        return [
            $this->buildDir === null ? null : $this->buildDir.'/stixx_openapi_command',
            $this->cacheDir === null ? null : $this->cacheDir.'/stixx_openapi_command/'.($this->buildId ?? 'build'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function build(): array
    {
        $areas = [];
        foreach (array_keys($this->routesLocator->getProvidedServices()) as $area) {
            $routes = $this->routesLocator->get((string) $area);
            if (!$routes instanceof RouteCollection) {
                continue;
            }

            foreach (array_keys($routes->all()) as $routeName) {
                $areas[$routeName] ??= (string) $area;
            }
        }

        return $areas;
    }
}
