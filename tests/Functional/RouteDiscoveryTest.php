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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Command routes must be registered without the application importing anything.
 *
 * Route discovery used to hang off a decorator on `routing.loader.attribute.directory`, so it only ran when
 * the application happened to load routes through that loader. An application using the current Symfony
 * skeleton's `config/routes.yaml` — which sets a `namespace` and therefore loads via `Psr4DirectoryLoader` —
 * got no command routes at all, with no error to explain why.
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

    private function bootDiscoveryKernel(): DiscoveryKernel
    {
        $kernel = new DiscoveryKernel('test', true);
        $kernel->addTestConfig(__DIR__.'/Resources/config/scenario.php');
        $kernel->boot();

        return $kernel;
    }
}
