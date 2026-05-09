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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Exception;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stixx\OpenApiCommandBundle\Exception\WrappedExceptionUnwrapper;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class WrappedExceptionUnwrapperTest extends TestCase
{
    public function testReturnsThrowableUnchangedWhenNotWrapped(): void
    {
        // Arrange
        $unwrapper = new WrappedExceptionUnwrapper();
        $throwable = new RuntimeException('plain');

        // Act
        $result = $unwrapper->unwrap($throwable);

        // Assert
        self::assertSame($throwable, $result);
    }

    public function testUnwrapsHandlerFailedExceptionToFirstCause(): void
    {
        // Arrange
        $unwrapper = new WrappedExceptionUnwrapper();
        $cause = new RuntimeException('the real cause');
        $wrapper = new HandlerFailedException(new Envelope(new stdClass()), [$cause]);

        // Act
        $result = $unwrapper->unwrap($wrapper);

        // Assert
        self::assertSame($cause, $result);
    }

    public function testUnwrapsFirstCauseWhenMultipleHandlersFailed(): void
    {
        // Arrange
        $unwrapper = new WrappedExceptionUnwrapper();
        $first = new RuntimeException('first handler');
        $second = new LogicException('second handler');
        $wrapper = new HandlerFailedException(new Envelope(new stdClass()), [$first, $second]);

        // Act
        $result = $unwrapper->unwrap($wrapper);

        // Assert
        self::assertSame($first, $result);
    }

    public function testUnwrapsNestedHandlerFailedExceptionRecursively(): void
    {
        // Arrange — simulates a handler that itself dispatched a command which then failed.
        $unwrapper = new WrappedExceptionUnwrapper();
        $rootCause = new RuntimeException('root');
        $inner = new HandlerFailedException(new Envelope(new stdClass()), [$rootCause]);
        $outer = new HandlerFailedException(new Envelope(new stdClass()), [$inner]);

        // Act
        $result = $unwrapper->unwrap($outer);

        // Assert
        self::assertSame($rootCause, $result);
    }

    public function testUnwrapsDelayedMessageHandlingException(): void
    {
        // Arrange — DelayedMessageHandlingException implements WrappedExceptionsInterface
        // through the same trait, so the unwrapper handles it identically.
        $unwrapper = new WrappedExceptionUnwrapper();
        $cause = new RuntimeException('delayed');
        $wrapper = new DelayedMessageHandlingException([$cause], new Envelope(new stdClass()));

        // Act
        $result = $unwrapper->unwrap($wrapper);

        // Assert
        self::assertSame($cause, $result);
    }
}
