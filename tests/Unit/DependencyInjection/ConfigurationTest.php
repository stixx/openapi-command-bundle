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
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        // Arrange
        $configuration = new Configuration();
        $processor = new Processor();

        // Act
        $config = $processor->processConfiguration($configuration, []);

        // Assert
        $expected = [
            'validation' => [
                'enabled' => true,
                'groups' => ['Default'],
            ],
            'cache_control' => 'no-store',
            'command_paths' => ['%kernel.project_dir%/src'],
            'openapi' => [
                'problem_details' => true,
            ],
        ];
        self::assertSame($expected, $config);
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
        ];

        // Act
        $config = $processor->processConfiguration($configuration, [$customConfig]);

        // Assert
        $expected = [
            'validation' => [
                'enabled' => false,
                'groups' => ['Custom', 'Special'],
            ],
            'cache_control' => 'no-store',
            'command_paths' => ['%kernel.project_dir%/src'],
            'openapi' => [
                'problem_details' => true,
            ],
        ];
        self::assertSame($expected, $config);
    }
}
