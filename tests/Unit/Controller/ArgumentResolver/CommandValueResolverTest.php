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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Stixx\OpenApiCommandBundle\Controller\ArgumentResolver\CommandValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class CommandValueResolverTest extends TestCase
{
    private MockObject&DenormalizerInterface $serializer;
    private CommandValueResolver $resolver;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(DenormalizerInterface::class);
        $this->resolver = new CommandValueResolver($this->serializer);
    }

    public function testResolveReturnsEmptyWhenNoAttribute(): void
    {
        // Arrange
        $request = new Request();
        $argument = new ArgumentMetadata('command', stdClass::class, false, false, null);

        // Act
        $result = $this->resolver->resolve($request, $argument);

        // Assert
        $this->assertSame([], iterator_to_array($result));
    }

    public function testResolveReturnsEmptyWhenTypeCannotBeResolved(): void
    {
        // Arrange
        $request = new Request();
        $attribute = new CommandObject(class: null);
        $argument = new ArgumentMetadata('command', null, false, false, null, false, [$attribute]);

        // Act
        $result = $this->resolver->resolve($request, $argument);

        // Assert
        $this->assertSame([], iterator_to_array($result));
    }

    /**
     * @param array<string, mixed> $expectedPayload
     */
    #[DataProvider('provideResolveRequests')]
    public function testResolveRequests(Request $request, ArgumentMetadata $argument, array $expectedPayload, string $expectedType): void
    {
        // Arrange
        $expectedObject = new stdClass();

        $this->serializer->expects($this->once())
            ->method('denormalize')
            ->with($expectedPayload, $expectedType)
            ->willReturn($expectedObject);

        // Act
        $result = $this->resolver->resolve($request, $argument);
        $actual = iterator_to_array($result);

        // Assert
        $this->assertCount(1, $actual);
        $this->assertSame($expectedObject, $actual[0]);
    }

    /**
     * @return iterable<string, array{Request, ArgumentMetadata, array<string, mixed>, string}>
     */
    public static function provideResolveRequests(): iterable
    {
        yield 'with attribute class' => [
            new Request(),
            new ArgumentMetadata('command', null, false, false, null, false, [new CommandObject(class: stdClass::class)]),
            [],
            stdClass::class,
        ];

        yield 'with argument type' => [
            new Request(),
            new ArgumentMetadata('command', stdClass::class, false, false, null, false, [new CommandObject()]),
            [],
            stdClass::class,
        ];

        yield 'with route class' => [
            new Request(attributes: ['_command_class' => stdClass::class]),
            new ArgumentMetadata('command', 'object', false, false, null, false, [new CommandObject()]),
            [],
            stdClass::class,
        ];

        yield 'with request body only' => [
            self::createJsonRequest('{"foo":"bar"}'),
            new ArgumentMetadata('command', stdClass::class, false, false, null, false, [new CommandObject()]),
            ['foo' => 'bar'],
            stdClass::class,
        ];

        yield 'with parameters only' => [
            new Request(
                query: ['queryParam' => 'queryValue'],
                attributes: ['routeParam' => 'routeValue', '_internal' => 'ignore']
            ),
            new ArgumentMetadata('command', stdClass::class, false, false, null, false, [new CommandObject()]),
            ['routeParam' => 'routeValue', 'queryParam' => 'queryValue'],
            stdClass::class,
        ];

        yield 'with precedence' => [
            self::createJsonRequest(
                '{"key":"bodyValue", "other":"bodyOther", "unique":"bodyUnique"}',
                ['key' => 'queryValue'],
                ['key' => 'routeValue', 'other' => 'routeOther']
            ),
            new ArgumentMetadata('command', stdClass::class, false, false, null, false, [new CommandObject()]),
            [
                'key' => 'queryValue',
                'other' => 'routeOther',
                'unique' => 'bodyUnique',
            ],
            stdClass::class,
        ];

        yield 'merges payload route and query' => [
            self::createJsonRequest(
                '{"body":"value", "override":"body"}',
                ['query' => 'value', 'override' => 'query'],
                ['route' => 'value', 'override' => 'route', '_not_included' => 'secret']
            ),
            new ArgumentMetadata('command', stdClass::class, false, false, null, false, [new CommandObject()]),
            [
                'body' => 'value',
                'override' => 'query',
                'route' => 'value',
                'query' => 'value',
            ],
            stdClass::class,
        ];
    }

    #[DataProvider('provideValidationFailures')]
    public function testResolveValidationFailures(Request $request, string $expectedMessage): void
    {
        // Arrange
        $attribute = new CommandObject(class: stdClass::class);
        $argument = new ArgumentMetadata('command', null, false, false, null, false, [$attribute]);

        // Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($expectedMessage);

        // Act
        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    /**
     * @return iterable<string, array{Request, string}>
     */
    public static function provideValidationFailures(): iterable
    {
        yield 'unsupported content type' => [
            new Request(server: ['HTTP_CONTENT_TYPE' => 'text/plain'], content: '{"foo":"bar"}'),
            'Unsupported Content-Type. Expecting application/json',
        ];

        yield 'invalid JSON' => [
            self::createJsonRequest('{invalid}'),
            'Invalid JSON body',
        ];
    }

    public function testResolveThrowsOnDenormalizationFailure(): void
    {
        // Arrange
        $request = new Request();
        $attribute = new CommandObject(class: stdClass::class);
        $argument = new ArgumentMetadata('command', null, false, false, null, false, [$attribute]);

        $this->serializer->expects($this->once())
            ->method('denormalize')
            ->willThrowException(new NotEncodableValueException('Fail'));

        // Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unable to map request to command: Fail');

        // Act
        iterator_to_array($this->resolver->resolve($request, $argument));
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $attributes
     */
    private static function createJsonRequest(string $content, array $query = [], array $attributes = []): Request
    {
        $request = new Request($query, [], $attributes, [], [], [], $content);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }
}
