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

use LogicException;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Routing\CommandRouteDiscovery;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Config\Resource\ReflectionClassResource;

final class CommandRouteDiscoveryTest extends TestCase
{
    private string $commandDir;

    private string $routingDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routingDir = dirname(__DIR__, 2).'/Mock/Routing';
        $this->commandDir = $this->routingDir.'/src';
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

    public function testGlobPatternsMatchAtAnyDepth(): void
    {
        // Act
        $names = array_keys($this->createDiscovery([$this->routingDir.'/contexts/**/Application/Command'])->discover()->all());

        // Assert
        self::assertEqualsCanonicalizing(['ctx_show_template', 'ctx_resolve_template', 'ctx_archive'], $names);
    }

    public function testASingleStarMatchesOneDirectoryLevel(): void
    {
        // Act
        $names = array_keys($this->createDiscovery([$this->routingDir.'/contexts/*/Application/Command'])->discover()->all());

        // Assert
        self::assertEqualsCanonicalizing(['ctx_show_template', 'ctx_resolve_template'], $names);
    }

    public function testRoutesAreOrderedMostSpecificFirstAcrossPaths(): void
    {
        // Arrange — the placeholder route's path is listed first, so only a sort across both paths puts the literal first.
        $paths = [
            $this->routingDir.'/contexts/Alpha/Application/Command',
            $this->routingDir.'/contexts/Beta/Application/Command',
        ];

        // Act
        $names = array_keys($this->createDiscovery($paths)->discover()->all());

        // Assert
        self::assertSame(['ctx_resolve_template', 'ctx_show_template'], $names);
    }

    public function testRelativePathsResolveThroughTheLocator(): void
    {
        // Act
        $names = array_keys($this->createDiscovery(['contexts/Gamma'])->discover()->all());

        // Assert
        self::assertSame(['ctx_archive'], $names);
    }

    public function testAGlobWithoutMatchesYieldsNoRoutes(): void
    {
        // Act
        $collection = $this->createDiscovery([$this->routingDir.'/contexts/*/Nothing'])->discover();

        // Assert
        self::assertCount(0, $collection->all());
    }

    public function testAMissingDirectoryIsAnError(): void
    {
        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('command_paths directory "'.$this->commandDir.'/does-not-exist" does not exist');

        // Act
        $this->createDiscovery([$this->commandDir.'/does-not-exist'])->discover();
    }

    public function testAMissingGlobPrefixIsAnError(): void
    {
        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('directory "'.$this->routingDir.'/missing" (from "'.$this->routingDir.'/missing/**/Command") does not exist');

        // Act
        $this->createDiscovery([$this->routingDir.'/missing/**/Command'])->discover();
    }

    public function testGlobAndClassResourcesAreRegistered(): void
    {
        // Arrange — the glob catches added or removed command files, the class resources catch edits.

        // Act
        $resources = $this->createDiscovery([$this->routingDir.'/contexts/**/Application/Command'])->discover()->getResources();

        // Assert
        self::assertNotEmpty(array_filter($resources, static fn (object $resource): bool => $resource instanceof GlobResource));
        $classes = array_map(strval(...), array_filter($resources, static fn (object $resource): bool => $resource instanceof ReflectionClassResource));
        self::assertCount(3, $classes);
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

    public function testAFileEntryIsAnError(): void
    {
        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is not a directory');

        // Act
        $this->createDiscovery([$this->commandDir.'/AnnotatedCommand.php'])->discover();
    }

    public function testATrailingSlashIsIgnored(): void
    {
        // Act
        $names = array_keys($this->createDiscovery([$this->routingDir.'/contexts/Gamma/'])->discover()->all());

        // Assert
        self::assertSame(['ctx_archive'], $names);
    }

    /**
     * @param list<string> $paths
     */
    private function createDiscovery(array $paths): CommandRouteDiscovery
    {
        $locator = new FileLocator([$this->routingDir]);
        $directoryLoader = new CommandRouteDirectoryLoader($locator, new CommandRouteClassLoader());

        return new CommandRouteDiscovery($directoryLoader, $locator, $paths);
    }
}
