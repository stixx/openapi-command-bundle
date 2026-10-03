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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRouteMap;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class NelmioAreaRouteMapTest extends TestCase
{
    private string $buildDir;

    private string $routesFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildDir = sys_get_temp_dir().'/stixx_area_map_'.bin2hex(random_bytes(6));
        $this->routesFile = $this->buildDir.'/routes.php';
        (new Filesystem())->dumpFile($this->routesFile, '<?php');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->buildDir);

        parent::tearDown();
    }

    public function testTheFirstAreaThatHasARouteWins(): void
    {
        // Arrange
        $map = new NelmioAreaRouteMap($this->locator(['default' => ['shared', 'only_default'], 'admin' => ['shared', 'only_admin']]));

        // Act & Assert
        self::assertSame('default', $map->areaOf('shared'));
        self::assertSame('default', $map->areaOf('only_default'));
        self::assertSame('admin', $map->areaOf('only_admin'));
        self::assertNull($map->areaOf('elsewhere'));
    }

    public function testReadsTheCachedMapWithoutAskingNelmioAgain(): void
    {
        // Arrange
        $this->map($this->locator(['default' => ['api_books']]))->areaOf('api_books');
        $map = $this->map($this->failingLocator());

        // Act
        $area = $map->areaOf('api_books');

        // Assert
        self::assertSame('default', $area);
    }

    public function testRebuildsWhenTheRoutesChange(): void
    {
        // Arrange
        $this->map($this->locator(['default' => ['api_books']]))->areaOf('api_books');
        touch($this->routesFile, time() + 10);
        $map = $this->map($this->locator(['admin' => ['api_books']]));

        // Act
        $area = $map->areaOf('api_books');

        // Assert
        self::assertSame('admin', $area);
    }

    public function testRebuildsWhenTheAreaConfigChanges(): void
    {
        // Arrange — the area config is not a route resource, so only its hash can tell the cached map is outdated.
        $this->map($this->locator(['default' => ['api_books']]), 'before')->areaOf('api_books');
        $map = $this->map($this->locator(['admin' => ['api_books']]), 'after');

        // Act
        $area = $map->areaOf('api_books');

        // Assert
        self::assertSame('admin', $area);
    }

    public function testDoesNotWarmUpWithoutABuildDirectory(): void
    {
        // Arrange
        $map = $this->map($this->failingLocator());

        // Act
        $preload = $map->warmUp($this->buildDir.'/cache');

        // Assert
        self::assertSame([], $preload);
        self::assertDirectoryDoesNotExist($this->buildDir.'/stixx_openapi_command');
    }

    public function testBuildsInMemoryWhenTheCacheCannotBeWritten(): void
    {
        // Arrange — a file where the cache directory should be.
        $map = new NelmioAreaRouteMap($this->locator(['default' => ['api_books']]), $this->router(), new ConfigCacheFactory(true), $this->routesFile);

        // Act
        $area = $map->areaOf('api_books');

        // Assert
        self::assertSame('default', $area);
    }

    public function testWarmsUpAsAnOptionalCacheWarmer(): void
    {
        // Arrange
        $map = $this->map($this->locator(['default' => ['api_books']]));

        // Act
        $map->warmUp($this->buildDir.'/cache', $this->buildDir);

        // Assert
        self::assertTrue($map->isOptional());
        self::assertFileExists($this->buildDir.'/stixx_openapi_command/nelmio_area_routes.areas.php');
        self::assertSame('default', $this->map($this->failingLocator())->areaOf('api_books'));
    }

    /**
     * @param ServiceLocator<RouteCollection> $locator
     */
    private function map(ServiceLocator $locator, string $areasHash = 'areas'): NelmioAreaRouteMap
    {
        return new NelmioAreaRouteMap($locator, $this->router(), new ConfigCacheFactory(true), $this->buildDir, $areasHash);
    }

    private function router(): RouterInterface
    {
        $routes = new RouteCollection();
        $routes->addResource(new FileResource($this->routesFile));

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        return $router;
    }

    /**
     * @param array<string, list<string>> $areas
     *
     * @return ServiceLocator<RouteCollection>
     */
    private function locator(array $areas): ServiceLocator
    {
        $factories = [];
        foreach ($areas as $area => $routeNames) {
            $factories[$area] = static function () use ($routeNames): RouteCollection {
                $routes = new RouteCollection();
                foreach ($routeNames as $routeName) {
                    $routes->add($routeName, new Route('/'.$routeName));
                }

                return $routes;
            };
        }

        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator($factories);

        return $locator;
    }

    /**
     * @return ServiceLocator<RouteCollection>
     */
    private function failingLocator(): ServiceLocator
    {
        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([
            'default' => static fn (): RouteCollection => throw new RuntimeException('Nelmio route collections must not be loaded'),
        ]);

        return $locator;
    }
}
