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

namespace Stixx\OpenApiCommandBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\DiscoveryKernel;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\RecordingLogger;
use Symfony\Component\Routing\RouterInterface;

final class EmptyCommandPathWarningTest extends AbstractKernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingLogger::$records = [];
    }

    #[WithoutErrorHandler]
    public function testWarnsInDebugModeAboutAnEntryThatMatchesNothing(): void
    {
        // Act
        $this->loadRoutes(true);

        // Assert
        $warnings = array_values(array_filter(RecordingLogger::$records, static fn (array $record): bool => $record['level'] === 'warning'));
        self::assertCount(1, $warnings);
        self::assertStringContainsString('matches no PHP files', $warnings[0]['message']);
        $path = $warnings[0]['context']['path'] ?? null;
        self::assertIsString($path);
        self::assertStringEndsWith('/*/Nothing', $path);
    }

    #[WithoutErrorHandler]
    public function testStaysQuietOutsideDebugMode(): void
    {
        // Act
        $this->loadRoutes(false);

        // Assert
        self::assertSame([], array_filter(RecordingLogger::$records, static fn (array $record): bool => $record['level'] === 'warning'));
    }

    private function loadRoutes(bool $debug): void
    {
        $kernel = new DiscoveryKernel('test', $debug);
        $kernel->addTestConfig(__DIR__.'/Resources/config/empty_command_path.php');
        $kernel->boot();

        $router = $kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertNotNull($router->getRouteCollection()->get('command_createbookcommand'));
    }
}
