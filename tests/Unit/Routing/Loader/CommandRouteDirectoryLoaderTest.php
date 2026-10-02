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

use PHPUnit\Framework\TestCase;
use ReflectionException;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\RouteCollection;

final class CommandRouteDirectoryLoaderTest extends TestCase
{
    public function testSupportsOnlyCustomType(): void
    {
        // Arrange
        $locator = new FileLocator(__DIR__);
        $classLoader = new CommandRouteClassLoader();
        $loader = new CommandRouteDirectoryLoader($locator, $classLoader);

        // Assert
        self::assertTrue($loader->supports(__DIR__, CommandRouteDirectoryLoader::TYPE));
        self::assertFalse($loader->supports(__DIR__, 'attribute'));
        self::assertFalse($loader->supports(__DIR__));
        self::assertFalse($loader->supports(123, CommandRouteDirectoryLoader::TYPE));
    }

    public function testDiscoversCommandsInNestedDirectories(): void
    {
        // Act
        $collection = $this->load('tree');

        // Assert
        self::assertNotNull($collection->get('tree_nested'));
    }

    public function testSkipsDotPrefixedDirectories(): void
    {
        // Act
        $collection = $this->load('tree');

        // Assert
        self::assertNull($collection->get('tree_hidden'));
    }

    public function testOnlyLoadsPhpFiles(): void
    {
        // Arrange — the tree holds a TextCommand.txt beside its commands.

        // Act
        $collection = $this->load('tree');

        // Assert
        self::assertSame(['tree_nested'], array_keys($collection->all()));
    }

    public function testFailsOnAClassThatCannotBeAutoloaded(): void
    {
        // Assert
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('MisplacedCommand');

        // Act
        $this->load('unloadable');
    }

    private function load(string $fixture): RouteCollection
    {
        $dir = dirname(__DIR__, 3).'/Mock/Routing/'.$fixture;
        $loader = new CommandRouteDirectoryLoader(new FileLocator($dir), new CommandRouteClassLoader());

        $collection = $loader->load($dir, CommandRouteDirectoryLoader::TYPE);
        self::assertInstanceOf(RouteCollection::class, $collection);

        return $collection;
    }
}
