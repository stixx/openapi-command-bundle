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
use stdClass;
use Stixx\OpenApiCommandBundle\Responder\ResponderChain;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Responder\ResponseContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResponderChainTest extends TestCase
{
    public function testRespondsWithTheFirstResponderThatSupportsTheContext(): void
    {
        // Arrange
        $context = new ResponseContext(new Request(), new stdClass(), 'foo');
        $expectedResponse = new Response();

        $responder1 = $this->createMock(ResponderInterface::class);
        $responder1->expects(self::once())->method('supports')->with($context)->willReturn(false);
        $responder1->expects(self::never())->method('respond');

        $responder2 = $this->createMock(ResponderInterface::class);
        $responder2->expects(self::once())->method('supports')->with($context)->willReturn(true);
        $responder2->expects(self::once())->method('respond')->with($context, 200)->willReturn($expectedResponse);

        $responder3 = $this->createMock(ResponderInterface::class);
        $responder3->expects(self::never())->method('supports');

        $chain = new ResponderChain([$responder1, $responder2, $responder3]);

        // Act
        $actualResponse = $chain->respond($context, 200);

        // Assert
        self::assertSame($expectedResponse, $actualResponse);
    }

    public function testThrowsWhenNoResponderSupportsTheContext(): void
    {
        // Arrange
        $responder = $this->createMock(ResponderInterface::class);
        $responder->method('supports')->willReturn(false);
        $chain = new ResponderChain([$responder]);

        // Assert
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('No supported responder found.');

        // Act
        $chain->respond(new ResponseContext(new Request(), new stdClass(), 'foo'), 200);
    }
}
