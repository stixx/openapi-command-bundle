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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Controller\ArgumentResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Stixx\OpenApiCommandBundle\Controller\ArgumentResolver\CommandValueResolver;
use Stixx\OpenApiCommandBundle\Tests\Mock\ExampleCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

class CommandValueResolverTest extends TestCase
{
    public function testReturnsEmptyWhenNoCommandObjectAttribute(): void
    {
        // Arrange
        $serializer = $this->createMock(SerializerInterface::class);
        $resolver = new CommandValueResolver($serializer);

        $request = new Request();
        $argument = new ArgumentMetadata(
            'command',
            ExampleCommand::class,
            false,
            false,
            null,
            false,
            []
        );

        // Act
        $result = iterator_to_array($resolver->resolve($request, $argument));

        // Assert
        self::assertSame([], $result);
    }

    public function testResolvesFromJsonBodyWithValidContentType(): void
    {
        // Arrange
        $command = new ExampleCommand(id: 1, name: 'john');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('deserialize')
            ->with('{"id":1,"name":"john"}', ExampleCommand::class, 'json')
            ->willReturn($command);

        $resolver = new CommandValueResolver($serializer);
        $request = new Request([], [], [], [], [], [], '{"id":1,"name":"john"}');
        $request->headers->set('Content-Type', 'application/json');

        $argument = new ArgumentMetadata(
            'command',
            ExampleCommand::class,
            false,
            false,
            null,
            false,
            [new CommandObject(ExampleCommand::class)]
        );

        // Act
        $result = iterator_to_array($resolver->resolve($request, $argument));

        // Assert
        self::assertCount(1, $result);
        self::assertSame($command, $result[0]);
    }

    #[DataProvider('invalidContentTypesProvider')]
    public function testRejectsNonJsonContentTypeWhenBodyPresent(string $contentType): void
    {
        // Arrange
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported Content-Type');

        $serializer = $this->createMock(SerializerInterface::class);
        $resolver = new CommandValueResolver($serializer);

        $request = new Request([], [], [], [], [], [], 'hello world');
        $request->headers->set('Content-Type', $contentType);

        $argument = new ArgumentMetadata(
            'command',
            ExampleCommand::class,
            false,
            false,
            null,
            false,
            [new CommandObject(ExampleCommand::class)]
        );

        // Act
        iterator_to_array($resolver->resolve($request, $argument));
    }

    public function testWrapsSerializerExceptionFromBodyIntoBadRequest(): void
    {
        // Arrange
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('deserialize')
            ->willThrowException(new NotEncodableValueException('bad json'));

        $resolver = new CommandValueResolver($serializer);
        $request = new Request([], [], [], [], [], [], '{"oops":');
        $request->headers->set('Content-Type', 'application/json');

        $argument = new ArgumentMetadata(
            'command',
            ExampleCommand::class,
            false,
            false,
            null,
            false,
            [new CommandObject(ExampleCommand::class)]
        );

        // Act
        iterator_to_array($resolver->resolve($request, $argument));
    }

    public function testResolvesFromRouteAndQueryWhenNoBody(): void
    {
        // Arrange
        $serializer = $this->createMock(SerializerInterface::class);

        // Expected combined data: route id=1 overridden by query id=2, plus name
        $expectedJson = json_encode(['id' => 2, 'name' => 'a'], JSON_THROW_ON_ERROR);
        $command = new ExampleCommand(id: 2, name: 'a');

        $serializer->expects(self::once())
            ->method('deserialize')
            ->with($expectedJson, ExampleCommand::class, 'json')
            ->willReturn($command);

        $resolver = new CommandValueResolver($serializer);

        $request = new Request(query: ['id' => 2, 'name' => 'a']);
        $request->attributes->add([
            'id' => 1,
            '_route' => 'ignore_this',
            '_command_class' => null,
        ]);

        $argument = new ArgumentMetadata(
            'command',
            ExampleCommand::class,
            false,
            false,
            null,
            false,
            [new CommandObject(ExampleCommand::class)]
        );

        // Act
        $result = iterator_to_array($resolver->resolve($request, $argument));

        // Assert
        self::assertCount(1, $result);
        self::assertSame($command, $result[0]);
    }

    public function testThrowsWhenNoBodyAndNoMappableParams(): void
    {
        // Arrange
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('No request body provided and no mappable route/query parameters found');

        $serializer = $this->createMock(SerializerInterface::class);
        $resolver = new CommandValueResolver($serializer);

        $request = new Request();
        $request->attributes->add(['_route' => 'anything']);

        $argument = new ArgumentMetadata(
            'command',
            ExampleCommand::class,
            false,
            false,
            null,
            false,
            [new CommandObject(ExampleCommand::class)]
        );

        // Act
        iterator_to_array($resolver->resolve($request, $argument));
    }

    public function testUsesArgumentTypeWhenAttributeHasNoClass(): void
    {
        // Arrange
        $command = new ExampleCommand(id: 10, name: 'x');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('deserialize')
            ->with('{"id":10,"name":"x"}', ExampleCommand::class, 'json')
            ->willReturn($command);

        $resolver = new CommandValueResolver($serializer);

        $request = new Request([], [], [], [], [], [], '{"id":10,"name":"x"}');
        $request->headers->set('Content-Type', 'application/json');

        $argument = new ArgumentMetadata(
            'command',
            ExampleCommand::class,
            false,
            false,
            null,
            false,
            [new CommandObject(null)]
        );

        // Act
        $result = iterator_to_array($resolver->resolve($request, $argument));

        // Assert
        self::assertCount(1, $result);
        self::assertSame($command, $result[0]);
    }

    public function testUsesRequestCommandClassWhenNoTypeDefined(): void
    {
        // Arrange
        $command = new ExampleCommand(id: 5, name: 'y');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('deserialize')
            ->with('{"id":5,"name":"y"}', ExampleCommand::class, 'json')
            ->willReturn($command);

        $resolver = new CommandValueResolver($serializer);

        $request = new Request([], [], [], [], [], [], '{"id":5,"name":"y"}');
        $request->headers->set('Content-Type', 'application/json');
        $request->attributes->set('_command_class', ExampleCommand::class);

        $argument = new ArgumentMetadata(
            'command',
            'object',
            false,
            false,
            null,
            false,
            [new CommandObject(null)]
        );

        // Act
        $result = iterator_to_array($resolver->resolve($request, $argument));

        // Assert
        self::assertCount(1, $result);
        self::assertSame($command, $result[0]);
    }

    public static function invalidContentTypesProvider(): iterable
    {
        yield 'plain text' => ['text/plain'];
        yield 'xml' => ['application/xml'];
        yield 'form' => ['application/x-www-form-urlencoded'];
    }
}
