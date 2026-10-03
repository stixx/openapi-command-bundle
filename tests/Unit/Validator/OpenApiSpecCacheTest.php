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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Validator;

use Nelmio\ApiDocBundle\ApiDocGenerator;
use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Post;
use OpenApi\Annotations\Response;
use OpenApi\Context;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stixx\OpenApiCommandBundle\Validator\OpenApiSpecCache;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class OpenApiSpecCacheTest extends TestCase
{
    private string $buildDir;

    private string $routesFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildDir = sys_get_temp_dir().'/stixx_spec_cache_'.bin2hex(random_bytes(6));
        $this->routesFile = $this->buildDir.'/routes.php';
        (new Filesystem())->dumpFile($this->routesFile, '<?php');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->buildDir);

        parent::tearDown();
    }

    public function testGeneratesTheDocumentOnceAndReadsItFromTheCacheAfterwards(): void
    {
        // Arrange
        $first = $this->describer('/books');
        $this->cache(new ApiDocGenerator([$first], []))->jsonFor('default');
        $second = $this->describer('/books');

        // Act
        $json = $this->cache(new ApiDocGenerator([$second], []))->jsonFor('default');

        // Assert
        self::assertSame(1, $first->calls);
        self::assertSame(0, $second->calls);
        self::assertStringContainsString('/books', $json);
    }

    public function testRegeneratesInDebugModeWhenATrackedFileChanges(): void
    {
        // Arrange
        $this->cache(new ApiDocGenerator([$this->describer('/books')], []), debug: true)->jsonFor('default');
        touch($this->routesFile, time() + 10);
        $describer = $this->describer('/authors');

        // Act
        $json = $this->cache(new ApiDocGenerator([$describer], []), debug: true)->jsonFor('default');

        // Assert
        self::assertSame(1, $describer->calls);
        self::assertStringContainsString('/authors', $json);
    }

    public function testKeepsTheDocumentInDebugModeWhileNothingChanged(): void
    {
        // Arrange
        $this->cache(new ApiDocGenerator([$this->describer('/books')], []), debug: true)->jsonFor('default');
        $describer = $this->describer('/authors');

        // Act
        $json = $this->cache(new ApiDocGenerator([$describer], []), debug: true)->jsonFor('default');

        // Assert
        self::assertSame(0, $describer->calls);
        self::assertStringContainsString('/books', $json);
    }

    public function testUsesTheAreasOwnGeneratorAndTheDefaultOneOtherwise(): void
    {
        // Arrange
        $default = new ApiDocGenerator([$this->describer('/default')], []);
        $internal = new ApiDocGenerator([$this->describer('/internal')], []);
        /** @var ServiceLocator<ApiDocGenerator> $generators */
        $generators = new ServiceLocator([
            'default' => static fn (): ApiDocGenerator => $default,
            'internal' => static fn (): ApiDocGenerator => $internal,
        ]);
        $cache = new OpenApiSpecCache($default, $generators);

        // Act & Assert
        self::assertStringContainsString('/internal', $cache->jsonFor('internal'));
        self::assertStringContainsString('/default', $cache->jsonFor('unknown'));
    }

    public function testWarmsEveryAreaAndSkipsOneThatFailsToGenerate(): void
    {
        // Arrange
        $default = new ApiDocGenerator([$this->describer('/default')], []);
        /** @var ServiceLocator<ApiDocGenerator> $generators */
        $generators = new ServiceLocator([
            'default' => static fn (): ApiDocGenerator => $default,
            'broken' => static fn (): ApiDocGenerator => throw new RuntimeException('Cannot describe this area'),
        ]);
        $cache = new OpenApiSpecCache($default, $generators, $this->router(), new ConfigCacheFactory(false), $this->buildDir);

        // Act
        $preload = $cache->warmUp($this->buildDir.'/cache', $this->buildDir);

        // Assert
        self::assertSame([], $preload);
        self::assertTrue($cache->isOptional());
        self::assertFileExists($this->buildDir.'/stixx_openapi_command/openapi.default.json');
        self::assertFileDoesNotExist($this->buildDir.'/stixx_openapi_command/openapi.broken.json');
    }

    public function testGeneratesInMemoryWhenTheCacheCannotBeWritten(): void
    {
        // Arrange — a file where the cache directory should be.
        $describer = $this->describer('/books');
        $cache = new OpenApiSpecCache(new ApiDocGenerator([$describer], []), null, $this->router(), new ConfigCacheFactory(false), $this->routesFile);

        // Act
        $json = $cache->jsonFor('default');

        // Assert
        self::assertSame(1, $describer->calls);
        self::assertStringContainsString('/books', $json);
    }

    private function cache(ApiDocGenerator $generator, bool $debug = false): OpenApiSpecCache
    {
        return new OpenApiSpecCache($generator, null, $this->router(), new ConfigCacheFactory($debug), $this->buildDir, null, $debug);
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
     * @return DescriberInterface&object{calls: int}
     */
    private function describer(string $path): DescriberInterface
    {
        return new class ($path) implements DescriberInterface {
            public int $calls = 0;

            public function __construct(private readonly string $path)
            {
            }

            public function describe(OpenApi $api): void
            {
                ++$this->calls;

                $api->info = new Info(['title' => 'Test', 'version' => '1.0.0', '_context' => new Context(['version' => '3.0.0'], null)]);
                $api->paths = [
                    new PathItem([
                        'path' => $this->path,
                        'post' => new Post([
                            'responses' => [new Response(['response' => 200, 'description' => 'ok', '_context' => new Context(['version' => '3.0.0'], null)])],
                            '_context' => new Context(['version' => '3.0.0'], null),
                        ]),
                        '_context' => new Context(['version' => '3.0.0'], null),
                    ]),
                ];
            }
        };
    }
}
