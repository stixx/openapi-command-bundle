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
use Stixx\OpenApiCommandBundle\Responder\NullableResponder;

final class NullableResponderTest extends TestCase
{
    public function testRespond(): void
    {
        // Arrange
        $responder = new NullableResponder();
        $status = 204;

        // Act
        $response = $responder->respond(null, $status);

        // Assert
        self::assertSame('', $response->getContent());
        self::assertSame($status, $response->getStatusCode());
    }

    #[DataProvider('supportsProvider')]
    public function testSupports(mixed $result, bool $expected): void
    {
        // Arrange
        $responder = new NullableResponder();

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
        yield 'null' => [
            null,
            true,
        ];

        yield 'empty string' => [
            '',
            true,
        ];

        yield 'false' => [
            false,
            true,
        ];

        yield 'zero' => [
            0,
            true,
        ];

        yield 'empty array' => [
            [],
            false,
        ];

        yield 'non-empty string' => [
            'foo',
            false,
        ];

        yield 'non-empty array' => [
            ['foo'],
            false,
        ];
    }
}
