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

use Nelmio\ApiDocBundle\DependencyInjection\NelmioApiDocExtension;
use Nelmio\ApiDocBundle\Routing\FilteredRouteCollectionBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\DependencyInjection\Compiler\CollectNelmioApiDocRoutesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
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

        $container->setDefinition('nelmio_api_doc.generator.default', new Definition(stdClass::class));

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

        self::assertTrue($container->hasDefinition('stixx_openapi_command.nelmio.generators_locator'));
        $generatorsDefinition = $container->getDefinition('stixx_openapi_command.nelmio.generators_locator');

        self::assertSame(ServiceLocator::class, $generatorsDefinition->getClass());
        self::assertTrue($generatorsDefinition->hasTag('container.service_locator'));

        $expectedGeneratorsMap = [
            'default' => new Reference('nelmio_api_doc.generator.default'),
        ];
        self::assertEquals([$expectedGeneratorsMap], $generatorsDefinition->getArguments());

        self::assertTrue($container->hasParameter('stixx_openapi_command.nelmio.path_patterns'));
        self::assertSame(
            ['default' => []],
            $container->getParameter('stixx_openapi_command.nelmio.path_patterns')
        );
    }

    /**
     * @param list<string> $expected
     * @param list<string> $notExpected
     */
    #[DataProvider('missingSetupProvider')]
    public function testProcessFailsWhenNelmioIsNotConfigured(bool $registered, array $expected, array $notExpected): void
    {
        // Arrange — a registered bundle has an extension; without config it still sets no areas parameter.
        $container = new ContainerBuilder();
        if ($registered) {
            $container->registerExtension(new NelmioApiDocExtension());
        }

        // Act
        $message = null;

        try {
            (new CollectNelmioApiDocRoutesPass())->process($container);
        } catch (LogicException $exception) {
            $message = $exception->getMessage();
        }

        // Assert
        self::assertNotNull($message, 'Expected a LogicException when nelmio_api_doc.areas is missing.');

        foreach ($expected as $needle) {
            self::assertStringContainsString($needle, $message);
        }

        foreach ($notExpected as $needle) {
            self::assertStringNotContainsString($needle, $message);
        }
    }

    /**
     * @return iterable<string, array{bool, list<string>, list<string>}>
     */
    public static function missingSetupProvider(): iterable
    {
        yield 'bundle not registered' => [
            false,
            [
                'NelmioApiDocBundle is not registered in config/bundles.php',
                'NelmioApiDocBundle::class',
                'config/packages/nelmio_api_doc.yaml',
                "path_patterns: ['^/api']",
            ],
            [],
        ];

        // Already registered, so the message must not ask for a config/bundles.php entry.
        yield 'bundle registered without config' => [
            true,
            [
                'NelmioApiDocBundle is registered but has no configuration',
                'config/packages/nelmio_api_doc.yaml',
            ],
            ['config/bundles.php'],
        ];
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
