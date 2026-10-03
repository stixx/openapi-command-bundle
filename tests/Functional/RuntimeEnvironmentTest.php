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

use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\RouteLoadCountingKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

/**
 * A container image is warmed with build-time env values and run with the real ones.
 */
final class RuntimeEnvironmentTest extends TestCase
{
    private const array ENV = ['API_URL', 'API_VERSION'];

    private string $cacheDir;

    /**
     * @var list<string>
     */
    private array $otherCacheDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sys_get_temp_dir().'/stixx_runtime_env_'.bin2hex(random_bytes(6));
        $this->warm(['API_URL' => 'http://localhost', 'API_VERSION' => 'build']);
    }

    protected function tearDown(): void
    {
        foreach (self::ENV as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
        (new Filesystem())->remove([$this->cacheDir, ...$this->otherCacheDirs]);

        parent::tearDown();

        for ($i = 0; $i < 5; ++$i) {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    #[WithoutErrorHandler]
    public function testValidatesUnderTheRuntimeServerUrlOfAnAppMountedBelowIt(): void
    {
        // Arrange
        $kernel = $this->boot(['API_URL' => 'https://api.example.com/v1', 'API_VERSION' => 'build']);
        $request = $this->createBookRequest('/v1/api/books', ['SCRIPT_NAME' => '/v1/index.php', 'SCRIPT_FILENAME' => '/app/public/index.php']);

        // Act
        $response = $kernel->handle($request);

        // Assert
        self::assertSame('/api/books', $request->getPathInfo());
        self::assertSame(201, $response->getStatusCode());
    }

    #[WithoutErrorHandler]
    public function testValidatesWhenTheServerPathDiffersFromTheAppsBaseUrl(): void
    {
        // Arrange
        $kernel = $this->boot(['API_URL' => 'https://api.example.com/v1', 'API_VERSION' => 'build']);

        // Act
        $response = $kernel->handle($this->createBookRequest('/api/books'));

        // Assert
        self::assertSame(201, $response->getStatusCode());
    }

    #[WithoutErrorHandler]
    public function testValidatesADocumentGeneratedWithAServerPathTheAppIsNotMountedBelow(): void
    {
        // Arrange
        $this->otherCacheDirs[] = $this->cacheDir;
        $this->cacheDir = sys_get_temp_dir().'/stixx_runtime_env_'.bin2hex(random_bytes(6));
        $kernel = $this->boot(['API_URL' => 'https://api.example.com/v1', 'API_VERSION' => 'build']);

        // Act
        $response = $kernel->handle($this->createBookRequest('/api/books'));

        // Assert
        self::assertSame(201, $response->getStatusCode());
    }

    #[WithoutErrorHandler]
    public function testRegeneratesOnceForADifferentRuntimeValueOfOtherDocumentation(): void
    {
        // Arrange
        $kernel = $this->boot(['API_URL' => 'http://localhost', 'API_VERSION' => 'runtime']);

        // Act
        $response = $kernel->handle($this->createBookRequest('/api/books'));

        // Assert
        self::assertSame(201, $response->getStatusCode());
        $documents = iterator_to_array((new Finder())->files()->in($kernel->getBuildDir().'/stixx_openapi_command')->name('openapi.v2.*.json')->notName('*.meta.json'));
        self::assertCount(2, $documents, 'The warmed document and one for the runtime value');
        foreach ($documents as $document) {
            self::assertStringNotContainsString('"servers"', $document->getContents());
        }
    }

    /**
     * @param array<string, string> $env
     */
    private function warm(array $env): void
    {
        $kernel = $this->boot($env);
        $warmer = $kernel->getContainer()->get('cache_warmer');
        self::assertInstanceOf(CacheWarmerAggregate::class, $warmer);
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());
    }

    /**
     * @param array<string, string> $env
     */
    private function boot(array $env): RouteLoadCountingKernel
    {
        foreach ($env as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
        }

        $kernel = new RouteLoadCountingKernel('test', false, $this->cacheDir);
        $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        $kernel->addTestConfig(__DIR__.'/Resources/config/env_documentation.php');
        $kernel->addTestConfig(__DIR__.'/Resources/config/warm_up.php');
        $kernel->boot();

        return $kernel;
    }

    /**
     * @param array<string, string> $server
     */
    private function createBookRequest(string $uri, array $server = []): Request
    {
        $request = Request::create(
            uri: $uri,
            method: 'POST',
            server: $server,
            content: json_encode(['title' => 'Refactoring', 'author' => 'Martin Fowler'], JSON_THROW_ON_ERROR),
        );
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }
}
