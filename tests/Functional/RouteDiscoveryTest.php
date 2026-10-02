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

use Nyholm\BundleTest\TestKernel;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Stixx\OpenApiCommandBundle\StixxOpenApiCommandBundle;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\Command\CreateBookCommand;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\DiscoveryKernel;
use Stixx\OpenApiCommandBundle\Tests\Functional\App\GlobImportKernel;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Command routes must be registered without the application importing anything. Discovery used to hang off a
 * decorator on `routing.loader.attribute.directory`, so it never ran for applications whose routes load
 * through another loader — the skeleton's `namespace` key routes through Psr4DirectoryLoader.
 */
final class RouteDiscoveryTest extends AbstractKernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        static::$class = null;
    }

    #[WithoutErrorHandler]
    public function testCommandRoutesAreRegisteredWithoutAnyRouteImports(): void
    {
        // Arrange
        $kernel = $this->bootDiscoveryKernel();

        // Act
        $router = $kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();

        // Assert
        $createBook = $routes->get('command_createbookcommand');
        self::assertNotNull($createBook, 'Expected the command route to be discovered');
        self::assertSame('/api/books', $createBook->getPath());
        self::assertSame(['POST'], $createBook->getMethods());

        self::assertNotNull($routes->get('command_updatebookcommand'));
        self::assertNotNull($routes->get('command_deletebookcommand'));

        // Without these the router cache would miss added or edited commands.
        $resources = $routes->getResources();
        self::assertNotEmpty(array_filter($resources, static fn (object $r): bool => $r instanceof GlobResource && str_contains((string) $r, '/*/Command')));
        self::assertNotEmpty(array_filter($resources, static fn (object $r): bool => $r instanceof ReflectionClassResource && str_contains((string) $r, CreateBookCommand::class)));
    }

    #[WithoutErrorHandler]
    public function testDiscoveredRoutesServeRequests(): void
    {
        // Arrange
        $kernel = $this->bootDiscoveryKernel();

        $request = Request::create(
            uri: '/api/books',
            method: 'POST',
            content: json_encode(['title' => 'Refactoring', 'author' => 'Martin Fowler'], JSON_THROW_ON_ERROR)
        );
        $request->headers->set('Content-Type', 'application/json');

        // Act
        $response = $kernel->handle($request);

        // Assert
        self::assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent() ?: 'null', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('Refactoring', $data['title'] ?? null);
    }

    #[WithoutErrorHandler]
    public function testCommandsCanBeImportedThroughAGlobWithDiscoveryOff(): void
    {
        // Arrange
        $kernel = new GlobImportKernel('test', true);
        $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        $kernel->boot();

        // Act
        $router = $kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();

        // Assert
        self::assertNotNull($routes->get('command_createbookcommand'));
        self::assertNotNull($routes->get('command_updatebookcommand'));
        self::assertNotNull($routes->get('command_deletebookcommand'));
    }

    #[WithoutErrorHandler]
    public function testBootingWithoutCommandPathsExplainsHowToConfigureThem(): void
    {
        // Arrange
        $kernel = new TestKernel('test', true);
        $kernel->addTestBundle(FrameworkBundle::class);
        $kernel->addTestBundle(StixxOpenApiCommandBundle::class);
        $kernel->addTestConfig(__DIR__.'/Resources/config/framework_only.php');

        // Assert
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "command_paths" under "stixx_openapi_command" must be configured');

        // Act
        $kernel->boot();
    }

    private function bootDiscoveryKernel(): DiscoveryKernel
    {
        $kernel = new DiscoveryKernel('test', true);
        $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        $kernel->boot();

        return $kernel;
    }
}
