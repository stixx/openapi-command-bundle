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

namespace Stixx\OpenApiCommandBundle\Validator;

use Nelmio\ApiDocBundle\ApiDocGenerator;
use ReflectionClass;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\Resource\ComposerResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * The OpenAPI document of each Nelmio area, generated once and cached as JSON, so validating a request does not run
 * Nelmio's describers (and load every route) again.
 *
 * @internal
 */
final class OpenApiSpecCache implements CacheWarmerInterface
{
    /**
     * @var array<string, string>
     */
    private array $specs = [];

    private ?string $generated = null;

    /**
     * @param ServiceLocator<ApiDocGenerator>|null $generatorsLocator
     */
    public function __construct(
        private readonly ApiDocGenerator $defaultGenerator,
        private readonly ?ServiceLocator $generatorsLocator = null,
        private readonly ?RouterInterface $router = null,
        private readonly ?ConfigCacheFactoryInterface $configCacheFactory = null,
        private readonly ?string $buildDir = null,
        private readonly ?string $containerFile = null,
        private readonly bool $debug = false,
    ) {
    }

    public function jsonFor(string $area): string
    {
        if ($this->debug) {
            return $this->load($area, $this->buildDir);
        }

        return $this->specs[$area] ??= $this->load($area, $this->buildDir);
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

        foreach ($this->areas() as $area) {
            try {
                $this->specs[$area] = $this->load($area, $buildDir);
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }

    private function load(string $area, ?string $dir): string
    {
        if ($dir === null || $this->configCacheFactory === null) {
            return $this->generate($area)[0];
        }

        $this->generated = null;

        try {
            $cache = $this->configCacheFactory->cache($dir.'/stixx_openapi_command/openapi.'.rawurlencode($area).'.json', function (ConfigCacheInterface $cache) use ($area): void {
                [$this->generated, $resources] = $this->generate($area);
                $cache->write($this->generated, $resources);
            });
        } catch (IOException) {
            return $this->generated ?? $this->generate($area)[0];
        }

        return $this->generated ?? (string) file_get_contents($cache->getPath());
    }

    /**
     * @return array{string, list<ResourceInterface>}
     */
    private function generate(string $area): array
    {
        $declaredBefore = $this->debug ? $this->declared() : [];

        $json = $this->generatorFor($area)->generate()->toJson();

        if (!$this->debug || $this->router === null) {
            return [$json, []];
        }

        $resources = array_values($this->router->getRouteCollection()->getResources());

        if ($this->containerFile !== null) {
            $resources[] = is_file($this->containerFile) ? new FileResource($this->containerFile) : new FileExistenceResource($this->containerFile);
        }

        $composer = new ComposerResource();
        /** @var list<string> $vendors */
        $vendors = $composer->getVendors();
        foreach (array_diff($this->declared(), $declaredBefore) as $class) {
            $file = (new ReflectionClass($class))->getFileName();
            if ($file === false || $this->isVendor($file, $vendors)) {
                continue;
            }

            $resources[] = new FileResource($file);
        }
        $resources[] = $composer;

        return [$json, $resources];
    }

    private function generatorFor(string $area): ApiDocGenerator
    {
        return $this->generatorsLocator?->has($area) === true ? $this->generatorsLocator->get($area) : $this->defaultGenerator;
    }

    /**
     * @return list<string>
     */
    private function areas(): array
    {
        return array_values(array_unique(['default', ...array_map('strval', array_keys($this->generatorsLocator?->getProvidedServices() ?? []))]));
    }

    /**
     * @return list<class-string>
     */
    private function declared(): array
    {
        return [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()];
    }

    /**
     * @param list<string> $vendors
     */
    private function isVendor(string $file, array $vendors): bool
    {
        foreach ($vendors as $vendor) {
            if (str_starts_with($file, $vendor.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
