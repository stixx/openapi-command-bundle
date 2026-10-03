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

namespace Stixx\OpenApiCommandBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\CountingRouteLoader;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\RouteLoadCountingKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

/**
 * Validating an API request used to generate the area's whole OpenAPI document, loading every route, on every request.
 */
final class ApiRequestTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sys_get_temp_dir().'/stixx_api_request_'.bin2hex(random_bytes(6));
        CountingRouteLoader::$loads = 0;
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);

        parent::tearDown();

        for ($i = 0; $i < 5; ++$i) {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideDebug(): iterable
    {
        yield 'prod' => [false];
        yield 'debug' => [true];
    }

    #[DataProvider('provideDebug')]
    #[WithoutErrorHandler]
    public function testAnApiRequestOnAWarmCacheLoadsNoRoutes(bool $debug): void
    {
        // Arrange
        $warming = $this->bootKernel($debug);
        $warmer = $warming->getContainer()->get('cache_warmer');
        self::assertInstanceOf(CacheWarmerAggregate::class, $warmer);
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($warming->getCacheDir(), $warming->getBuildDir());
        CountingRouteLoader::$loads = 0;

        // Act
        $response = $this->bootKernel($debug)->handle($this->createBookRequest());

        // Assert
        self::assertSame(201, $response->getStatusCode());
        self::assertSame(0, CountingRouteLoader::$loads);
    }

    #[WithoutErrorHandler]
    public function testWarmingCachesTheDocumentOfEveryArea(): void
    {
        // Arrange
        $kernel = $this->bootKernel(false, __DIR__.'/Resources/config/two_areas.php');
        $warmer = $kernel->getContainer()->get('cache_warmer');
        self::assertInstanceOf(CacheWarmerAggregate::class, $warmer);
        $warmer->enableOptionalWarmers();

        // Act
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        // Assert
        $books = $kernel->getBuildDir().'/stixx_openapi_command/openapi.'.hash('xxh128', 'books').'.json';
        self::assertFileExists($kernel->getBuildDir().'/stixx_openapi_command/openapi.'.hash('xxh128', 'default').'.json');
        self::assertFileExists($books);
        $document = (string) file_get_contents($books);
        self::assertStringContainsString('/api/books/{id}', $document);
        self::assertStringContainsString('"BookRequest"', $document, 'The second area must be described as fully as the first');
    }

    #[WithoutErrorHandler]
    public function testAnInvalidRequestIsStillRejectedFromTheCachedDocument(): void
    {
        // Arrange
        $this->bootKernel(false)->handle($this->createBookRequest());
        $request = Request::create(uri: '/api/books', method: 'POST', content: '{"title": 42}');
        $request->headers->set('Content-Type', 'application/json');

        // Act
        $response = $this->bootKernel(false)->handle($request);

        // Assert
        self::assertSame(400, $response->getStatusCode());
    }

    private function bootKernel(bool $debug, ?string $extraConfig = null): RouteLoadCountingKernel
    {
        $kernel = new RouteLoadCountingKernel('test', $debug, $this->cacheDir);
        $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        if ($extraConfig !== null) {
            $kernel->addTestConfig($extraConfig);
        }
        $kernel->boot();

        return $kernel;
    }

    private function createBookRequest(): Request
    {
        $request = Request::create(
            uri: '/api/books',
            method: 'POST',
            content: json_encode(['title' => 'Refactoring', 'author' => 'Martin Fowler'], JSON_THROW_ON_ERROR),
        );
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }
}
