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
use Stixx\OpenApiCommandBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        // Arrange
        $configuration = new Configuration();
        $processor = new Processor();

        // Act
        $config = $processor->processConfiguration($configuration, [['command_paths' => ['%kernel.project_dir%/src/Command']]]);

        // Assert
        $expected = [
            'command_paths' => ['%kernel.project_dir%/src/Command'],
            'validation' => [
                'enabled' => true,
                'groups' => ['Default'],
            ],
            'cache_control' => 'no-store',
            'openapi' => [
                'problem_details' => true,
            ],
        ];
        self::assertSame($expected, $config);
    }

    public function testCommandPathsMustBeConfigured(): void
    {
        // Arrange
        $configuration = new Configuration();
        $processor = new Processor();

        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "command_paths" under "stixx_openapi_command" must be configured: Directories or glob patterns holding command DTOs');

        // Act
        $processor->processConfiguration($configuration, []);
    }

    public function testCommandPathsRejectAnEmptyEntry(): void
    {
        // Arrange
        $configuration = new Configuration();
        $processor = new Processor();

        // Assert
        $this->expectException(InvalidConfigurationException::class);

        // Act
        $processor->processConfiguration($configuration, [['command_paths' => ['']]]);
    }

    public function testCommandPathsAcceptGlobPatterns(): void
    {
        // Arrange
        $configuration = new Configuration();
        $processor = new Processor();

        // Act
        $config = $processor->processConfiguration($configuration, [['command_paths' => ['%kernel.project_dir%/src/**/Application/Command']]]);

        // Assert
        self::assertSame(['%kernel.project_dir%/src/**/Application/Command'], $config['command_paths']);
    }

    public function testCommandPathsCanBeEmptiedToDisableDiscovery(): void
    {
        // Arrange
        $configuration = new Configuration();
        $processor = new Processor();

        // Act
        $config = $processor->processConfiguration($configuration, [['command_paths' => []]]);

        // Assert
        self::assertSame([], $config['command_paths']);
    }

    public function testCustomConfig(): void
    {
        // Arrange
        $configuration = new Configuration();
        $processor = new Processor();
        $customConfig = [
            'validation' => [
                'enabled' => false,
                'groups' => ['Custom', 'Special'],
            ],
            'command_paths' => [],
        ];

        // Act
        $config = $processor->processConfiguration($configuration, [$customConfig]);

        // Assert
        $expected = [
            'validation' => [
                'enabled' => false,
                'groups' => ['Custom', 'Special'],
            ],
            'command_paths' => [],
            'cache_control' => 'no-store',
            'openapi' => [
                'problem_details' => true,
            ],
        ];
        self::assertSame($expected, $config);
    }
}
