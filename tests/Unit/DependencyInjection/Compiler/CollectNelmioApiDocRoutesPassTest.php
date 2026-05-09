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

use Nelmio\ApiDocBundle\Routing\FilteredRouteCollectionBuilder;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\DependencyInjection\Compiler\CollectNelmioApiDocRoutesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Routing\RouteCollection;

final class CollectNelmioApiDocRoutesPassTest extends TestCase
{
    public function testProcessWithAreas(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $container->setParameter('nelmio_api_doc.areas', ['default', 'internal']);

        // The "default" area uses Nelmio's no-filter shortcut (a Definition without a factory),
        // which means there are no path_patterns to extract.
        $container->setDefinition('nelmio_api_doc.routes.default', new Definition(stdClass::class));
        // 'nelmio_api_doc.routes.internal' is missing on purpose — it should be skipped silently.

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

        self::assertTrue($container->hasParameter('stixx_openapi_command.nelmio.path_patterns'));
        self::assertSame(
            ['default' => []],
            $container->getParameter('stixx_openapi_command.nelmio.path_patterns')
        );
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
        self::assertFalse($container->hasParameter('stixx_openapi_command.nelmio.path_patterns'));
    }

    public function testExtractsPathPatternsFromFilteredRouteCollectionBuilderFactory(): void
    {
        // Arrange — mimic the service Nelmio registers for an area with a `path_patterns` filter:
        // its factory is [Definition(FilteredRouteCollectionBuilder, [reflector, $area, $areaConfig]), 'filter'].
        $container = new ContainerBuilder();
        $container->setParameter('nelmio_api_doc.areas', ['default', 'admin']);

        $defaultRoutes = (new Definition(RouteCollection::class))
            ->setFactory([
                (new Definition(FilteredRouteCollectionBuilder::class))
                    ->setArguments([
                        new Reference('nelmio_api_doc.controller_reflector'),
                        'default',
                        ['path_patterns' => ['^/api']],
                    ]),
                'filter',
            ]);
        $container->setDefinition('nelmio_api_doc.routes.default', $defaultRoutes);

        $adminRoutes = (new Definition(RouteCollection::class))
            ->setFactory([
                (new Definition(FilteredRouteCollectionBuilder::class))
                    ->setArguments([
                        new Reference('nelmio_api_doc.controller_reflector'),
                        'admin',
                        ['path_patterns' => ['^/admin/api', '^/internal']],
                    ]),
                'filter',
            ]);
        $container->setDefinition('nelmio_api_doc.routes.admin', $adminRoutes);

        $pass = new CollectNelmioApiDocRoutesPass();

        // Act
        $pass->process($container);

        // Assert
        self::assertSame(
            [
                'default' => ['^/api'],
                'admin' => ['^/admin/api', '^/internal'],
            ],
            $container->getParameter('stixx_openapi_command.nelmio.path_patterns')
        );
    }
}
