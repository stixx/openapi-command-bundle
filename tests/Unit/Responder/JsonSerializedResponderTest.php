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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Responder;

use ArrayIterator;
use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\Responder\JsonSerializedResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

final class JsonSerializedResponderTest extends TestCase
{
    public function testRespond(): void
    {
        // Arrange
        $serializer = $this->createMock(SerializerInterface::class);
        $responder = new JsonSerializedResponder($serializer);
        $result = ['foo' => 'bar'];
        $status = 201;
        $json = '{"foo":"bar"}';

        $serializer->expects(self::once())
            ->method('serialize')
            ->with($result, JsonEncoder::FORMAT)
            ->willReturn($json);

        // Act
        $response = $responder->respond($result, $status);

        // Assert
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame($json, $response->getContent());
        self::assertSame($status, $response->getStatusCode());
    }

    #[DataProvider('supportsProvider')]
    public function testSupports(mixed $result, bool $expected): void
    {
        // Arrange
        $serializer = $this->createMock(SerializerInterface::class);
        $responder = new JsonSerializedResponder($serializer);

        // Act
        $actual = $responder->supports($result);

        // Assert
        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function supportsProvider(): iterable
    {
        yield 'object' => [
            new stdClass(),
            true,
        ];

        yield 'array' => [
            ['foo' => 'bar'],
            true,
        ];

        yield 'Traversable' => [
            new ArrayIterator([]),
            true,
        ];

        yield 'JsonSerializable' => [
            new class () implements JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return [];
                }
            },
            false,
        ];

        yield 'string' => [
            'foo',
            false,
        ];

        yield 'int' => [
            123,
            false,
        ];

        yield 'null' => [
            null,
            false,
        ];
    }
}
