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

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Responder\ResponderChain;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Symfony\Component\HttpFoundation\Response;

final class ResponderChainTest extends TestCase
{
    public function testRespondCallsFirstSupportingResponder(): void
    {
        // Arrange
        $result = 'foo';
        $status = 200;
        $expectedResponse = new Response();

        $responder1 = $this->createMock(ResponderInterface::class);
        $responder1->expects(self::once())->method('supports')->with($result)->willReturn(false);
        $responder1->expects(self::never())->method('respond');

        $responder2 = $this->createMock(ResponderInterface::class);
        $responder2->expects(self::once())->method('supports')->with($result)->willReturn(true);
        $responder2->expects(self::once())->method('respond')->with($result, $status)->willReturn($expectedResponse);

        $responder3 = $this->createMock(ResponderInterface::class);
        $responder3->expects(self::never())->method('supports');

        $chain = new ResponderChain([$responder1, $responder2, $responder3]);

        // Act
        $actualResponse = $chain->respond($result, $status);

        // Assert
        self::assertSame($expectedResponse, $actualResponse);
    }

    public function testRespondThrowsExceptionWhenNoResponderSupportsResult(): void
    {
        // Arrange
        $result = 'foo';
        $status = 200;

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())->method('supports')->with($result)->willReturn(false);

        $chain = new ResponderChain([$responder]);

        // Assert
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('No supported responder found.');

        // Act
        $chain->respond($result, $status);
    }

    public function testSupportsAlwaysReturnsTrue(): void
    {
        // Arrange
        $chain = new ResponderChain([]);

        // Act & Assert
        self::assertTrue($chain->supports('anything'));
        self::assertTrue($chain->supports(null));
    }
}
