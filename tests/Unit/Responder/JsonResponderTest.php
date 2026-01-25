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

use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\Responder\JsonResponder;
use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonResponderTest extends TestCase
{
    public function testRespond(): void
    {
        // Arrange
        $responder = new JsonResponder();
        $result = ['foo' => 'bar'];
        $status = 200;

        // Act
        $response = $responder->respond($result, $status);

        // Assert
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(json_encode($result), $response->getContent());
        self::assertSame($status, $response->getStatusCode());
    }

    #[DataProvider('supportsProvider')]
    public function testSupports(mixed $result, bool $expected): void
    {
        // Arrange
        $responder = new JsonResponder();

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
        yield 'JsonSerializable' => [
            new class () implements JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return [];
                }
            },
            true,
        ];

        yield 'array' => [
            [],
            false,
        ];

        yield 'object' => [
            new stdClass(),
            false,
        ];

        yield 'string' => [
            'foo',
            false,
        ];

        yield 'null' => [
            null,
            false,
        ];
    }
}
