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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\DependencyInjection\StixxOpenApiCommandExtension;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Routing\CommandRouteDiscovery;
use Stixx\OpenApiCommandBundle\Routing\Loader\RouterLoaderDecorator;
use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class StixxOpenApiCommandExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $extension = new StixxOpenApiCommandExtension();

        // Act
        $extension->load([['command_paths' => ['%kernel.project_dir%/src/Command']]], $container);

        // Assert
        self::assertTrue($container->hasParameter('stixx_openapi_command.validation.enabled'));
        self::assertTrue($container->getParameter('stixx_openapi_command.validation.enabled'));
        self::assertSame(['Default'], $container->getParameter('stixx_openapi_command.validation.groups'));

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(ResponderInterface::class, $autoconfigured);
        self::assertTrue($autoconfigured[ResponderInterface::class]->hasTag(ResponderInterface::TAG_NAME));

        self::assertArrayHasKey(ValidatorInterface::class, $autoconfigured);
        self::assertTrue($autoconfigured[ValidatorInterface::class]->hasTag(ValidatorInterface::TAG_NAME));

        self::assertSame(['%kernel.project_dir%/src/Command'], $container->getParameter('stixx_openapi_command.command_paths'));
        self::assertTrue($container->hasDefinition(CommandRouteDiscovery::class));
        self::assertTrue($container->hasDefinition(RouterLoaderDecorator::class));
    }

    public function testEmptyCommandPathsLeaveDiscoveryUnregistered(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $extension = new StixxOpenApiCommandExtension();

        // Act
        $extension->load([['command_paths' => []]], $container);

        // Assert
        self::assertFalse($container->hasDefinition(CommandRouteDiscovery::class));
        self::assertFalse($container->hasDefinition(RouterLoaderDecorator::class));
    }

    public function testPrependToleratesConfigWithoutCommandPaths(): void
    {
        // Arrange — prepend() runs before every bundle has contributed its config, so a required key may still be missing.
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('stixx_openapi_command', ['openapi' => ['problem_details' => false]]);
        $extension = new StixxOpenApiCommandExtension();

        // Act
        $extension->prepend($container);

        // Assert
        self::assertSame([], $container->getExtensionConfig('nelmio_api_doc'));
    }

    public function testPrependRegistersProblemDetailsByDefault(): void
    {
        // Arrange
        $container = new ContainerBuilder();
        $extension = new StixxOpenApiCommandExtension();

        // Act
        $extension->prepend($container);

        // Assert
        self::assertNotSame([], $container->getExtensionConfig('nelmio_api_doc'));
    }

    public function testGetAlias(): void
    {
        // Arrange
        $extension = new StixxOpenApiCommandExtension();

        // Act & Assert
        self::assertSame('stixx_openapi_command', $extension->getAlias());
    }
}
