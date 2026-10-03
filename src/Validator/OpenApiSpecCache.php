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
use Psr\Log\LoggerInterface;
use ReflectionClass;
use stdClass;
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
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * The OpenAPI document of each Nelmio area, generated once and cached as JSON, so validating a request does not run
 * Nelmio's describers (and load every route) again.
 *
 * @internal
 */
final class OpenApiSpecCache implements CacheWarmerInterface
{
    private const array OPERATIONS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /**
     * @var array<string, string>
     */
    private array $specs = [];

    /**
     * @var array<string, true>
     */
    private array $describedFiles = [];

    /**
     * @param ServiceLocator<ApiDocGenerator>|null $generatorsLocator
     * @param array<string, array<string, mixed>> $areaEnv per area, the runtime values of the env vars its documentation uses
     */
    public function __construct(
        private readonly ApiDocGenerator $defaultGenerator,
        private readonly ?ServiceLocator $generatorsLocator = null,
        private readonly ?RouterInterface $router = null,
        private readonly ?ConfigCacheFactoryInterface $configCacheFactory = null,
        private readonly ?string $buildDir = null,
        private readonly ?string $containerFile = null,
        private readonly bool $debug = false,
        private readonly array $areaEnv = [],
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $cacheDir = null,
    ) {
    }

    public function jsonFor(string $area): string
    {
        if ($this->debug) {
            return $this->load($area, [$this->buildDir, $this->cacheDir], true);
        }

        return $this->specs[$area] ??= $this->load($area, [$this->buildDir, $this->cacheDir], true);
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
                $this->specs[$area] = $this->load($area, [$buildDir], false);
            } catch (Throwable $exception) {
                $this->logger?->warning('Could not cache the OpenAPI document of Nelmio area "{area}"; it is generated on first use instead: {message}', [
                    'area' => $area,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
            }
        }

        return [];
    }

    /**
     * @param list<string|null> $dirs where to look for the document, then where to write it, in order
     */
    private function load(string $area, array $dirs, bool $atRuntime): string
    {
        $dirs = array_values(array_unique(array_filter($dirs, static fn (?string $dir): bool => $dir !== null && $dir !== '')));
        if ($dirs === [] || $this->configCacheFactory === null) {
            return $this->generate($area)[0];
        }

        $file = '/stixx_openapi_command/openapi.v2.'.hash('xxh128', $area).$this->envFingerprint($area).'.json';
        foreach ($dirs as $dir) {
            $stale = false;
            $cache = $this->configCacheFactory->cache($dir.$file, static function () use (&$stale): void {
                $stale = true;
            });

            $json = !$stale && is_file($cache->getPath()) ? file_get_contents($cache->getPath()) : false;
            if (is_string($json)) {
                return $json;
            }
        }

        try {
            [$json, $resources] = $this->generate($area);
        } catch (Throwable $exception) {
            $warmed = $atRuntime ? $this->warmedDocument($area, $dirs[0]) : null;
            if ($warmed === null) {
                throw $exception;
            }

            $this->logger?->warning('Could not generate the OpenAPI document of Nelmio area "{area}" for the runtime environment; validating against the one cached at warm-up: {message}', [
                'area' => $area,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $warmed;
        }

        foreach ($dirs as $dir) {
            try {
                $this->configCacheFactory->cache($dir.$file, static function (ConfigCacheInterface $cache) use ($json, $resources): void {
                    $cache->write($json, $resources);
                });

                break;
            } catch (IOException) {
                continue;
            }
        }

        return $json;
    }

    private function warmedDocument(string $area, string $dir): ?string
    {
        foreach (glob($dir.'/stixx_openapi_command/openapi.v2.'.hash('xxh128', $area).'*.json') ?: [] as $file) {
            if (str_ends_with($file, '.meta.json')) {
                continue;
            }

            $json = file_get_contents($file);
            if (is_string($json)) {
                return $json;
            }
        }

        return null;
    }

    /**
     * @return array{string, list<ResourceInterface>}
     */
    private function generate(string $area): array
    {
        $declaredBefore = $this->debug ? $this->declared() : [];

        $generator = $this->generatorFor($area);
        if ($this->debug && $generator instanceof ResetInterface) { // @phpstan-ignore instanceof.alwaysTrue
            $generator->reset();
        }

        try {
            $json = $this->withoutServers($generator->generate()->toJson());
        } finally {
            if ($this->debug) {
                $this->trackDescribedFiles($declaredBefore);
            }
        }

        if (!$this->debug || $this->router === null) {
            return [$json, []];
        }

        $resources = array_values($this->router->getRouteCollection()->getResources());

        if ($this->containerFile !== null) {
            $resources[] = is_file($this->containerFile) ? new FileResource($this->containerFile) : new FileExistenceResource($this->containerFile);
        }

        $this->trackSchemaClasses($json);

        foreach (array_keys($this->describedFiles) as $file) {
            $resources[] = new FileResource($file);
        }
        $resources[] = new ComposerResource();

        return [$json, $resources];
    }

    /**
     * @param list<class-string> $declaredBefore
     */
    private function trackDescribedFiles(array $declaredBefore): void
    {
        $this->track(array_diff($this->declared(), $declaredBefore));
    }

    private function trackSchemaClasses(string $json): void
    {
        $document = json_decode($json, true);
        $components = is_array($document) ? ($document['components'] ?? null) : null;
        $schemas = is_array($components) ? ($components['schemas'] ?? null) : null;
        if (!is_array($schemas) || $schemas === []) {
            return;
        }

        $this->track(array_filter(
            $this->declared(),
            static fn (string $class): bool => isset($schemas[substr((string) strrchr('\\'.$class, '\\'), 1)]),
        ));
    }

    /**
     * @param array<class-string> $classes
     */
    private function track(array $classes): void
    {
        /** @var list<string> $vendors */
        $vendors = (new ComposerResource())->getVendors();
        foreach ($classes as $class) {
            $file = (new ReflectionClass($class))->getFileName();
            if ($file !== false && !$this->isVendor($file, $vendors)) {
                $this->describedFiles[$file] = true;
            }
        }
    }

    private function envFingerprint(string $area): string
    {
        $values = $this->areaEnv[$area] ?? [];

        return $values === [] ? '' : '.'.hash('xxh128', serialize($values));
    }

    private function withoutServers(string $json): string
    {
        $document = json_decode($json, flags: JSON_THROW_ON_ERROR);
        if (!$document instanceof stdClass) {
            return $json;
        }

        unset($document->servers);
        foreach ((array) ($document->paths ?? []) as $pathItem) {
            if (!$pathItem instanceof stdClass) {
                continue;
            }

            unset($pathItem->servers);
            foreach (self::OPERATIONS as $method) {
                if (($pathItem->{$method} ?? null) instanceof stdClass) {
                    unset($pathItem->{$method}->servers);
                }
            }
        }

        return json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
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
