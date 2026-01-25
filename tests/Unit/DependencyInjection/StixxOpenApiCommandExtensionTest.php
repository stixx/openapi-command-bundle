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
        $extension->load([], $container);

        // Assert
        self::assertTrue($container->hasParameter('stixx_openapi_command.validation.enabled'));
        self::assertTrue($container->getParameter('stixx_openapi_command.validation.enabled'));
        self::assertSame(['Default'], $container->getParameter('stixx_openapi_command.validation.groups'));

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(ResponderInterface::class, $autoconfigured);
        self::assertTrue($autoconfigured[ResponderInterface::class]->hasTag(ResponderInterface::TAG_NAME));

        self::assertArrayHasKey(ValidatorInterface::class, $autoconfigured);
        self::assertTrue($autoconfigured[ValidatorInterface::class]->hasTag(ValidatorInterface::TAG_NAME));
    }

    public function testGetAlias(): void
    {
        // Arrange
        $extension = new StixxOpenApiCommandExtension();

        // Act & Assert
        self::assertSame('stixx_openapi_command', $extension->getAlias());
    }
}
