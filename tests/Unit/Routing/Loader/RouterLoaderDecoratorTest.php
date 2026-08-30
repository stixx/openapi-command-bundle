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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing\Loader;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Routing\CommandRouteDiscovery;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\RouterLoaderDecorator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouterLoaderDecoratorTest extends TestCase
{
    private string $commandDir;
    private Loader&MockObject $inner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandDir = dirname(__DIR__, 3).'/Mock/Routing/src';
        $this->inner = $this->createMock(Loader::class);
    }

    public function testCommandRoutesAreAddedToTheApplicationCollection(): void
    {
        // Arrange — an application collection that knows nothing about commands.
        $appRoutes = new RouteCollection();
        $appRoutes->add('app_home', new Route('/'));
        $this->inner->method('load')->willReturn($appRoutes);

        // Act
        $collection = $this->createDecorator()->load('routing.yaml');

        // Assert
        self::assertInstanceOf(RouteCollection::class, $collection);
        $names = array_keys($collection->all());
        self::assertContains('app_home', $names);
        self::assertContains('api_test', $names, 'Expected discovered command routes to be added');
    }

    public function testRoutesTheApplicationAlreadyDeclaredArePreserved(): void
    {
        // Arrange — the application imported a command explicitly, so the name is already taken.
        $explicit = new Route('/explicitly/imported');
        $appRoutes = new RouteCollection();
        $appRoutes->add('api_test', $explicit);
        $this->inner->method('load')->willReturn($appRoutes);

        // Act
        $collection = $this->createDecorator()->load('routing.yaml');

        // Assert
        self::assertInstanceOf(RouteCollection::class, $collection);
        self::assertSame($explicit, $collection->get('api_test'));
    }

    public function testCacheResourcesAreAddedToTheApplicationCollection(): void
    {
        // Arrange
        $this->inner->method('load')->willReturn(new RouteCollection());

        // Act
        $collection = $this->createDecorator()->load('routing.yaml');

        // Assert — without these the router cache never notices a command file changing.
        self::assertInstanceOf(RouteCollection::class, $collection);
        self::assertNotEmpty($collection->getResources());
    }

    public function testNonRouteCollectionResultsPassThroughUntouched(): void
    {
        // Arrange
        $this->inner->method('load')->willReturn(null);

        // Act & Assert
        self::assertNull($this->createDecorator()->load('routing.yaml'));
    }

    public function testSupportsDelegatesToInner(): void
    {
        // Arrange
        $this->inner->expects(self::once())
            ->method('supports')
            ->with('resource', 'attribute')
            ->willReturn(true);

        // Assert
        self::assertTrue($this->createDecorator()->supports('resource', 'attribute'));
    }

    private function createDecorator(): RouterLoaderDecorator
    {
        $directoryLoader = new CommandRouteDirectoryLoader(
            new FileLocator([$this->commandDir]),
            new CommandRouteClassLoader(),
        );

        return new RouterLoaderDecorator(
            $this->inner,
            new CommandRouteDiscovery($directoryLoader, [$this->commandDir]),
        );
    }
}
