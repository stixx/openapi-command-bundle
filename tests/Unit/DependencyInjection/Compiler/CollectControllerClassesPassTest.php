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
use Stixx\OpenApiCommandBundle\DependencyInjection\Compiler\CollectControllerClassesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class CollectControllerClassesPassTest extends TestCase
{
    public function testProcess(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        $controller1 = new Definition(stdClass::class);
        $controller1->addTag('controller.service_arguments');
        $container->setDefinition('controller1', $controller1);

        $controller2 = new Definition('NonExistentClass');
        $controller2->addTag('controller.service_arguments');
        $container->setDefinition('controller2', $controller2);

        $otherService = new Definition(stdClass::class);
        $container->setDefinition('other_service', $otherService);

        $pass = new CollectControllerClassesPass();

        // Act
        $pass->process($container);

        // Assert
        self::assertTrue($container->hasParameter('stixx_openapi_command.controller_classes'));
        self::assertSame([stdClass::class => true], $container->getParameter('stixx_openapi_command.controller_classes'));
    }
}
