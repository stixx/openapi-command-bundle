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
use Stixx\OpenApiCommandBundle\Routing\CommandRouteDiscovery;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Symfony\Component\Config\FileLocator;

final class CommandRouteDiscoveryTest extends TestCase
{
    private string $commandDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandDir = dirname(__DIR__, 2).'/Mock/Routing/src';
    }

    public function testDiscoversCommandsInConfiguredPaths(): void
    {
        // Act
        $names = array_keys($this->createDiscovery([$this->commandDir])->discover()->all());

        // Assert
        self::assertContains('api_test', $names);
    }

    public function testCommandRoutesAreOrderedMostSpecificFirst(): void
    {
        // Arrange — the fixture filenames scan as CollectionItemCommand (/api/items/{id}) before
        // CollectionLiteralCommand (/api/items/featured), so without specificity ordering the
        // placeholder route would be registered first and swallow the literal one.

        // Act
        $names = array_keys($this->createDiscovery([$this->commandDir])->discover()->all());
        $itemRoutes = array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'items_')));

        // Assert
        self::assertSame(['items_featured', 'items_item'], $itemRoutes);
    }

    public function testDiscoveredRoutesKeepTheirCacheResources(): void
    {
        // Arrange — resources are what invalidate the router cache when a command file changes.

        // Act
        $resources = $this->createDiscovery([$this->commandDir])->discover()->getResources();

        // Assert
        self::assertNotEmpty($resources, 'Sorting must not drop the loader resources');
    }

    public function testNonExistentPathsAreSkipped(): void
    {
        // Act
        $collection = $this->createDiscovery([$this->commandDir.'/does-not-exist'])->discover();

        // Assert
        self::assertCount(0, $collection->all());
    }

    public function testScanHappensOnlyOnce(): void
    {
        // Arrange
        $discovery = $this->createDiscovery([$this->commandDir]);

        // Act
        $first = $discovery->discover();
        $second = $discovery->discover();

        // Assert
        self::assertSame($first, $second);
    }

    /**
     * @param list<string> $paths
     */
    private function createDiscovery(array $paths): CommandRouteDiscovery
    {
        $directoryLoader = new CommandRouteDirectoryLoader(
            new FileLocator([$this->commandDir]),
            new CommandRouteClassLoader(),
        );

        return new CommandRouteDiscovery($directoryLoader, $paths);
    }
}
