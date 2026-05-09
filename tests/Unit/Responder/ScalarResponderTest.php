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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\Responder\ScalarResponder;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ScalarResponderTest extends TestCase
{
    /**
     * @param string|int|float|bool $result
     */
    #[DataProvider('respondProvider')]
    public function testRespondProducesJsonResponseWithEncodedScalar(mixed $result, string $expectedBody): void
    {
        // Arrange
        $responder = new ScalarResponder();

        // Act
        $response = $responder->respond($result, 200);

        // Assert
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expectedBody, $response->getContent());
        self::assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function respondProvider(): iterable
    {
        yield 'integer' => [42, '42'];
        yield 'float' => [3.14, '3.14'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'string' => ['hello', '"hello"'];
        yield 'empty string' => ['', '""'];
        yield 'zero' => [0, '0'];
    }

    #[DataProvider('supportsProvider')]
    public function testSupports(mixed $result, bool $expected): void
    {
        // Arrange
        $responder = new ScalarResponder();

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
        yield 'integer' => [42, true];
        yield 'float' => [3.14, true];
        yield 'string' => ['hello', true];
        yield 'true' => [true, true];
        yield 'false' => [false, true];
        yield 'zero' => [0, true];
        yield 'empty string' => ['', true];

        yield 'null' => [null, false];
        yield 'array' => [[], false];
        yield 'object' => [new stdClass(), false];
    }
}
