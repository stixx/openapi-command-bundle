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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\RouteDescriber;

use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Stixx\OpenApiCommandBundle\RouteDescriber\CommandRouteDescriber;
use Stixx\OpenApiCommandBundle\Tests\Mock\Commands\CreateItemCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\Commands\DeleteItemCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\Commands\GetItemsCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\Commands\ReplaceItemCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\Commands\UpdateItemCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\FakeInlineParameterDescriber;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactoryInterface;
use Symfony\Component\Routing\Route;

class CommandRouteDescriberTest extends TestCase
{
    public function testDescribeDoesNothingWhenNoCommandClass(): void
    {
        // Arrange
        $api = new OA\OpenApi([]);
        $route = new Route('/items');

        $inlineParameterDescriber = new FakeInlineParameterDescriber();
        $argumentMetadataFactory = new class () implements ArgumentMetadataFactoryInterface {
            public function createArgumentMetadata(array|object|string $controller, ?ReflectionFunctionAbstract $reflector = null): array
            {
                return [];
            }
        };
        $commandRouteDescriber = $this->createCommandRouteDescriber($argumentMetadataFactory, $inlineParameterDescriber);
        $reflection = $this->createControllerReflection();

        // Act
        $commandRouteDescriber->describe($api, $route, $reflection);

        // Assert
        self::assertSame(Generator::UNDEFINED, $api->paths);
        self::assertSame([], $inlineParameterDescriber->calls);
    }

    public function testDescribeBuildsOperationMergesAnnotationsAndRunsInlineDescribers(): void
    {
        // Arrange
        $api = new OA\OpenApi([]);
        $route = new Route('/items', defaults: [
            '_command_class' => CreateItemCommand::class,
        ], methods: ['POST']);

        $inlineParameterDescriber = new FakeInlineParameterDescriber();
        $argumentMetadataFactory = $this->createArgumentMetadataFactory(CreateItemCommand::class);
        $commandRouteDescriber = $this->createCommandRouteDescriber($argumentMetadataFactory, $inlineParameterDescriber);
        $reflection = $this->createControllerReflection();

        // Act
        $commandRouteDescriber->describe($api, $route, $reflection);

        // Assert
        $operation = $this->getOperationFor($api, '/items', 'POST');
        self::assertNotNull($operation, 'POST operation should be created');
        self::assertSame('create_item_full', $operation->operationId);

        self::assertIsArray($operation->tags);
        self::assertContains('items', $operation->tags);

        self::assertIsArray($operation->responses);
        self::assertNotEmpty($operation->responses);
        $response = $operation->responses[0];
        self::assertSame(201, $response->response);

        $tag = Util::getTag($api, 'items');
        self::assertSame('items', $tag->name);

        self::assertCount(1, $inlineParameterDescriber->calls);
        [$argMeta, $op] = $inlineParameterDescriber->calls[0];
        self::assertInstanceOf(ArgumentMetadata::class, $argMeta);
        self::assertSame($operation, $op);
    }

    #[DataProvider('commandsProvider')]
    public function testDescriberUsesFullFeaturedCommandsViaProvider(
        string $httpMethod,
        string $routePath,
        string $commandClass,
        string $expectedOperationId,
        int $expectedFirstResponseCode,
        string $expectedTag,
    ): void {
        // Arrange
        $api = new OA\OpenApi([]);
        $route = new Route($routePath, defaults: [
            '_command_class' => $commandClass,
        ], methods: [$httpMethod]);

        $inlineParameterDescriber = new FakeInlineParameterDescriber();
        $argumentMetadataFactory = $this->createArgumentMetadataFactory($commandClass);
        $commandRouteDescriber = $this->createCommandRouteDescriber($argumentMetadataFactory, $inlineParameterDescriber);
        $reflection = $this->createControllerReflection();

        // Act
        $commandRouteDescriber->describe($api, $route, $reflection);

        // Assert
        $operation = $this->getOperationFor($api, $routePath, $httpMethod);

        self::assertNotNull($operation, sprintf('Operation for %s should be created', $httpMethod));
        self::assertSame($expectedOperationId, $operation->operationId);

        self::assertIsArray($operation->responses);
        self::assertNotEmpty($operation->responses);
        $firstResponse = $operation->responses[0];
        self::assertSame($expectedFirstResponseCode, $firstResponse->response);

        self::assertIsArray($operation->tags);
        self::assertContains($expectedTag, $operation->tags);

        self::assertCount(1, $inlineParameterDescriber->calls);
    }

    public static function commandsProvider(): iterable
    {
        yield 'GET full featured' => ['GET', '/items', GetItemsCommand::class, 'list_items_full', 200, 'items'];
        yield 'POST full featured' => ['POST', '/items', CreateItemCommand::class, 'create_item_full', 201, 'items'];
        yield 'PUT full featured' => ['PUT', '/items/{id}', ReplaceItemCommand::class, 'replace_item_full', 200, 'admin'];
        yield 'PATCH full featured' => ['PATCH', '/items/{id}', UpdateItemCommand::class, 'update_item_full', 200, 'admin'];
        yield 'DELETE full featured' => ['DELETE', '/items/{id}', DeleteItemCommand::class, 'delete_item_full', 204, 'admin'];
    }

    private function createControllerReflection(): ReflectionMethod
    {
        $controller = new class () {
            public function __invoke(object $command): void
            {
            }
        };

        return new ReflectionMethod($controller, '__invoke');
    }

    private function createArgumentMetadataFactory(string $class): ArgumentMetadataFactoryInterface
    {
        return new readonly class ($class) implements ArgumentMetadataFactoryInterface {
            public function __construct(private string $class)
            {
            }

            public function createArgumentMetadata(array|object|string $controller, ?ReflectionFunctionAbstract $reflector = null): array
            {
                return [new ArgumentMetadata('cmd', $this->class, false, false, null)];
            }
        };
    }

    private function createCommandRouteDescriber(ArgumentMetadataFactoryInterface $factory, FakeInlineParameterDescriber $inline): CommandRouteDescriber
    {
        return new CommandRouteDescriber($factory, [$inline]);
    }

    private function getOperationFor(OA\OpenApi $api, string $routePath, string $httpMethod): ?OA\Operation
    {
        $pathItem = Util::getPath($api, $routePath);

        return match (strtolower($httpMethod)) {
            'get' => $pathItem->get,
            'post' => $pathItem->post,
            'put' => $pathItem->put,
            'patch' => $pathItem->patch,
            'delete' => $pathItem->delete,
            default => null,
        };
    }
}
