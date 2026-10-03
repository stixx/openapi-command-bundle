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
use OpenApi\Annotations\OpenApi;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Stixx\OpenApiCommandBundle\Tests\Mock\Validator\DescribedModel;
use Stixx\OpenApiCommandBundle\Tests\Mock\Validator\PathDescriber;
use Stixx\OpenApiCommandBundle\Tests\Mock\Validator\PreloadedModel;
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

    public function testTracksAModelForEveryAreaThatDescribesIt(): void
    {
        // Arrange
        $default = new ApiDocGenerator([$this->describer('/default', DescribedModel::class)], []);
        $books = new ApiDocGenerator([$this->describer('/books')], []);
        /** @var ServiceLocator<ApiDocGenerator> $generators */
        $generators = new ServiceLocator([
            'default' => static fn (): ApiDocGenerator => $default,
            'books' => static fn (): ApiDocGenerator => $books,
        ]);
        $cache = new OpenApiSpecCache($default, $generators, $this->router(), new ConfigCacheFactory(true), $this->buildDir, null, true);

        // Act
        $cache->warmUp($this->buildDir.'/cache', $this->buildDir);

        // Assert
        $model = (string) (new ReflectionClass(DescribedModel::class))->getFileName();
        self::assertStringContainsString($model, (string) file_get_contents($this->buildDir.'/stixx_openapi_command/openapi.v2.'.hash('xxh128', 'default').'.json.meta'));
        self::assertStringContainsString($model, (string) file_get_contents($this->buildDir.'/stixx_openapi_command/openapi.v2.'.hash('xxh128', 'books').'.json.meta'));
    }

    public function testTracksAModelLoadedBeforeGenerationThatTheDocumentDescribes(): void
    {
        // Arrange
        class_exists(PreloadedModel::class);
        $cache = $this->cache(new ApiDocGenerator([$this->describer('/books', schema: 'PreloadedModel')], []), debug: true);

        // Act
        $cache->jsonFor('default');

        // Assert
        $model = (string) (new ReflectionClass(PreloadedModel::class))->getFileName();
        self::assertStringContainsString($model, (string) file_get_contents($this->buildDir.'/stixx_openapi_command/openapi.v2.'.hash('xxh128', 'default').'.json.meta'));
    }

    public function testRegeneratesFromTheSameGeneratorInADebugWorker(): void
    {
        // Arrange
        $describer = $this->describer('/books');
        $cache = $this->cache(new ApiDocGenerator([$describer], []), debug: true);
        $cache->jsonFor('default');
        $describer->path = '/authors';
        touch($this->routesFile, time() + 10);

        // Act
        $json = $cache->jsonFor('default');

        // Assert
        self::assertStringContainsString('/authors', $json);
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
        self::assertFileExists($this->buildDir.'/stixx_openapi_command/openapi.v2.'.hash('xxh128', 'default').'.json');
        self::assertFileDoesNotExist($this->buildDir.'/stixx_openapi_command/openapi.v2.'.hash('xxh128', 'broken').'.json');
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

    public function testLeavesServersOutOfTheDocumentAtEveryLevel(): void
    {
        // Arrange
        $cache = new OpenApiSpecCache(new ApiDocGenerator([$this->describer('/books', servers: true)], []));

        // Act
        $json = $cache->jsonFor('default');

        // Assert
        self::assertStringContainsString('/books', $json);
        self::assertStringNotContainsString('servers', $json);
        self::assertStringNotContainsString('example.com', $json);
    }

    public function testNamesTheCacheAfterTheRuntimeValuesOfTheAreasEnv(): void
    {
        // Arrange
        $cache = new OpenApiSpecCache(new ApiDocGenerator([$this->describer('/books')], []), null, $this->router(), new ConfigCacheFactory(false), $this->buildDir, null, false, ['default' => ['API_VERSION' => '2.0']]);

        // Act
        $cache->jsonFor('default');

        // Assert
        self::assertFileExists($this->buildDir.'/stixx_openapi_command/openapi.v2.'.hash('xxh128', 'default').'.'.hash('xxh128', serialize(['API_VERSION' => '2.0'])).'.json');
    }

    public function testLogsAnAreaItCouldNotWarm(): void
    {
        // Arrange
        $default = new ApiDocGenerator([$this->describer('/default')], []);
        /** @var ServiceLocator<ApiDocGenerator> $generators */
        $generators = new ServiceLocator([
            'default' => static fn (): ApiDocGenerator => $default,
            'broken' => static fn (): ApiDocGenerator => throw new RuntimeException('Environment variable not found: "API_URL".'),
        ]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('Could not cache the OpenAPI document of Nelmio area "{area}"'), self::callback(static fn (array $context): bool => $context['area'] === 'broken' && is_string($context['message']) && str_contains($context['message'], 'API_URL')));
        $cache = new OpenApiSpecCache($default, $generators, $this->router(), new ConfigCacheFactory(false), $this->buildDir, logger: $logger);

        // Act
        $cache->warmUp($this->buildDir.'/cache', $this->buildDir);

        // Assert - handled by mock expectations
    }

    public function testCachesInTheCacheDirectoryWhenTheBuildDirectoryIsReadOnly(): void
    {
        // Arrange — a file where the build directory's cache folder should be.
        $describer = $this->describer('/books');
        $this->cacheWithReadOnlyBuildDir(new ApiDocGenerator([$describer], []))->jsonFor('default');
        $next = $this->describer('/books');

        // Act
        $json = $this->cacheWithReadOnlyBuildDir(new ApiDocGenerator([$next], []))->jsonFor('default');

        // Assert
        self::assertSame(1, $describer->calls);
        self::assertSame(0, $next->calls);
        self::assertStringContainsString('/books', $json);
    }

    public function testFallsBackToTheWarmedDocumentWhenTheRuntimeEnvironmentCannotBeDescribed(): void
    {
        // Arrange
        $this->cacheWithEnv(new ApiDocGenerator([$this->describer('/books')], []), ['API_VERSION' => 'warm-up'])->warmUp($this->buildDir.'/cache', $this->buildDir);
        $this->cacheWithEnv(new ApiDocGenerator([$this->describer('/at-runtime')], []), ['API_VERSION' => 'runtime'])->jsonFor('default');
        $failing = $this->failingDescriber();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('validating against the one cached at warm-up'));

        // Act
        $json = $this->cacheWithEnv(new ApiDocGenerator([$failing], []), ['API_VERSION' => null], $logger)->jsonFor('default');

        // Assert
        self::assertStringContainsString('/books', $json);
        self::assertStringNotContainsString('/at-runtime', $json);
    }

    public function testKeepsTheFallbackSoTheNextRequestDoesNotDescribeAgain(): void
    {
        // Arrange
        $this->cacheWithEnv(new ApiDocGenerator([$this->describer('/books')], []), ['API_VERSION' => 'build'])->warmUp($this->buildDir.'/cache', $this->buildDir);
        $this->cacheWithEnv(new ApiDocGenerator([$this->failingDescriber()], []), ['API_VERSION' => null])->jsonFor('default');
        $failing = $this->failingDescriber();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        // Act
        $json = $this->cacheWithEnv(new ApiDocGenerator([$failing], []), ['API_VERSION' => null], $logger)->jsonFor('default');

        // Assert
        self::assertSame(0, $failing->calls);
        self::assertStringContainsString('/books', $json);
    }

    public function testDoesNotFallBackInDebugMode(): void
    {
        // Arrange
        $this->cacheWithEnv(new ApiDocGenerator([$this->describer('/books')], []), ['API_VERSION' => 'build'], debug: true)->warmUp($this->buildDir.'/cache', $this->buildDir);
        $cache = $this->cacheWithEnv(new ApiDocGenerator([$this->failingDescriber()], []), ['API_VERSION' => null], debug: true);

        // Act & Assert
        $this->expectExceptionMessage('Environment variable not found');
        $cache->jsonFor('default');
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
     * @param class-string|null $loads
     */
    private function describer(string $path, ?string $loads = null, ?string $schema = null, bool $servers = false): PathDescriber
    {
        return new PathDescriber($path, $loads, $schema, $servers);
    }

    private function cacheWithReadOnlyBuildDir(ApiDocGenerator $generator): OpenApiSpecCache
    {
        return new OpenApiSpecCache($generator, null, $this->router(), new ConfigCacheFactory(false), $this->routesFile, null, false, [], null, $this->buildDir.'/var-cache');
    }

    /**
     * @param array<string, mixed> $env
     */
    private function cacheWithEnv(ApiDocGenerator $generator, array $env, ?LoggerInterface $logger = null, bool $debug = false): OpenApiSpecCache
    {
        return new OpenApiSpecCache($generator, null, $this->router(), new ConfigCacheFactory($debug), $this->buildDir, null, $debug, ['default' => $env], $logger);
    }

    /**
     * @return DescriberInterface&object{calls: int}
     */
    private function failingDescriber(): DescriberInterface
    {
        return new class () implements DescriberInterface {
            public int $calls = 0;

            public function describe(OpenApi $api): void
            {
                ++$this->calls;

                throw new RuntimeException('Environment variable not found: "API_VERSION".');
            }
        };
    }
}
