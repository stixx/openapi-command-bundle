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
use Stixx\OpenApiCommandBundle\Routing\Loader\AttributeDirectoryLoaderDecorator;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;

final class AttributeDirectoryLoaderDecoratorTest extends TestCase
{
    private string $projectDir;
    private AttributeDirectoryLoader&MockObject $inner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = dirname(__DIR__, 3).'/Mock/Routing';
        $this->inner = $this->createMock(AttributeDirectoryLoader::class);
    }

    public function testLoadAugmentsOnlyOnceByScanningSrc(): void
    {
        // Arrange
        $this->inner->expects(self::exactly(2))
            ->method('load')
            ->with($this->anything(), $this->anything())
            ->willReturnOnConsecutiveCalls(new RouteCollection(), new RouteCollection());

        $locator = new FileLocator([$this->projectDir]);
        $commandClassLoader = new CommandRouteClassLoader();
        $decorator = new AttributeDirectoryLoaderDecorator($this->inner, $locator, $commandClassLoader, $this->projectDir);

        // Act & Assert
        $first = $decorator->load('ignored');
        $route = array_keys($first->all());
        self::assertContains('api_test', $route, 'Expected route from AnnotatedCommand to be merged');

        $second = $decorator->load('ignored');
        self::assertCount(0, $second->all(), 'Second load returns inner collection without augmentation');
    }

    public function testCommandRoutesAreOrderedMostSpecificFirst(): void
    {
        // Arrange — the fixture filenames scan as CollectionItemCommand (/api/items/{id}) before
        // CollectionLiteralCommand (/api/items/featured), so without specificity ordering the
        // placeholder route would be registered first and swallow the literal one.
        $this->inner->method('load')->willReturn(new RouteCollection());

        $locator = new FileLocator([$this->projectDir]);
        $decorator = new AttributeDirectoryLoaderDecorator($this->inner, $locator, new CommandRouteClassLoader(), $this->projectDir);

        // Act
        $names = array_keys($decorator->load('ignored')->all());
        $itemRoutes = array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'items_')));

        // Assert
        self::assertSame(['items_featured', 'items_item'], $itemRoutes);
    }

    public function testSupportsDelegatesToInner(): void
    {
        // Arrange
        $this->inner->expects(self::once())
            ->method('supports')
            ->with('resource', 'attribute')
            ->willReturn(true);

        $locator = new FileLocator([$this->projectDir]);
        $decorator = new AttributeDirectoryLoaderDecorator($this->inner, $locator, new CommandRouteClassLoader(), $this->projectDir);

        // Assert
        self::assertTrue($decorator->supports('resource', 'attribute'));
    }
}
