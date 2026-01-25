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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\DependencyInjection\Compiler\CollectNelmioApiDocRoutesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class CollectNelmioApiDocRoutesPassTest extends TestCase
{
    public function testProcessWithAreas(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('nelmio_api_doc.areas', ['default', 'internal']);

        $container->setDefinition('nelmio_api_doc.routes.default', new Definition(stdClass::class));
        // 'nelmio_api_doc.routes.internal' is missing

        $pass = new CollectNelmioApiDocRoutesPass();

        // Act
        $pass->process($container);

        // Assert
        self::assertTrue($container->hasDefinition('stixx_openapi_command.nelmio.routes_locator'));
        $definition = $container->getDefinition('stixx_openapi_command.nelmio.routes_locator');

        self::assertSame(ServiceLocator::class, $definition->getClass());
        self::assertTrue($definition->hasTag('container.service_locator'));

        $expectedMap = [
            'default' => new Reference('nelmio_api_doc.routes.default'),
        ];
        self::assertEquals([$expectedMap], $definition->getArguments());
    }

    public function testProcessWithoutParameter(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $pass = new CollectNelmioApiDocRoutesPass();

        // Act
        $pass->process($container);

        // Assert
        self::assertFalse($container->hasDefinition('stixx_openapi_command.nelmio.routes_locator'));
    }
}
