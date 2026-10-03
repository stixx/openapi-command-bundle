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
use Psr\Container\ContainerInterface;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\CountingRouteLoader;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\RouteLoadCountingKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

/**
 * Finding a request's Nelmio area used to rebuild the whole route collection on every request.
 */
final class AreaLookupTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sys_get_temp_dir().'/stixx_area_lookup_'.bin2hex(random_bytes(6));
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

    #[WithoutErrorHandler]
    public function testAWarmedCacheResolvesTheAreaWithoutLoadingRoutes(): void
    {
        // Arrange
        $warming = $this->bootKernel(warmUp: true);
        $warmer = $warming->getContainer()->get('cache_warmer');
        self::assertInstanceOf(CacheWarmerAggregate::class, $warmer);
        $warmer->enableOptionalWarmers();
        $warmer->warmUp($warming->getCacheDir(), $warming->getBuildDir());
        CountingRouteLoader::$loads = 0;

        // Act
        $area = $this->areaOf($this->bootKernel(), 'command_createbookcommand');

        // Assert
        self::assertSame('default', $area);
        self::assertSame(0, CountingRouteLoader::$loads);
    }

    #[WithoutErrorHandler]
    public function testWithoutWarmingTheRoutesAreLoadedOnceAndThenReused(): void
    {
        // Arrange
        $first = $this->bootKernel();
        self::assertSame('default', $this->areaOf($first, 'command_createbookcommand'));
        $loadsByTheFirstRequest = CountingRouteLoader::$loads;
        CountingRouteLoader::$loads = 0;

        // Act
        $area = $this->areaOf($this->bootKernel(), 'command_deletebookcommand');

        // Assert
        self::assertSame(1, $loadsByTheFirstRequest);
        self::assertSame('default', $area);
        self::assertSame(0, CountingRouteLoader::$loads);
    }

    private function bootKernel(bool $warmUp = false): RouteLoadCountingKernel
    {
        $kernel = new RouteLoadCountingKernel('test', false, $this->cacheDir);
        if ($warmUp) {
            $kernel->addTestConfig(__DIR__.'/Resources/config/warm_up.php');
        }
        $kernel->boot();

        return $kernel;
    }

    private function areaOf(RouteLoadCountingKernel $kernel, string $routeName): ?string
    {
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $checker = $container->get(NelmioAreaRoutesChecker::class);
        self::assertInstanceOf(NelmioAreaRoutesChecker::class, $checker);

        $request = Request::create('/api/books', 'POST');
        $request->attributes->set('_route', $routeName);

        return $checker->areaFor($request);
    }
}
