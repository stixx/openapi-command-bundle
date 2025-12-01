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
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteClassLoader;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Symfony\Component\Config\FileLocator;

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
}
