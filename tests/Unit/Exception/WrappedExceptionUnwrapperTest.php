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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stixx\OpenApiCommandBundle\Exception\WrappedExceptionUnwrapper;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

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

    #[DataProvider('wrappedExceptionScenarios')]
    public function testUnwrapsWrappedExceptionToFirstLeafCause(Throwable $wrapped, Throwable $expectedCause): void
    {
        // Arrange
        $unwrapper = new WrappedExceptionUnwrapper();

        // Act
        $result = $unwrapper->unwrap($wrapped);

        // Assert
        self::assertSame($expectedCause, $result);
    }

    /**
     * @return iterable<string, array{0: Throwable, 1: Throwable}>
     */
    public static function wrappedExceptionScenarios(): iterable
    {
        $envelope = new Envelope(new stdClass());

        $singleCause = new RuntimeException('the real cause');
        yield 'single-handler HFE returns first cause' => [
            new HandlerFailedException($envelope, [$singleCause]),
            $singleCause,
        ];

        $firstFailure = new RuntimeException('first handler');
        $secondFailure = new LogicException('second handler');
        yield 'multi-handler HFE returns the first wrapped exception' => [
            new HandlerFailedException($envelope, [$firstFailure, $secondFailure]),
            $firstFailure,
        ];

        // Simulates a handler that dispatched a command which then itself failed.
        $rootCause = new RuntimeException('root');
        $innerWrapper = new HandlerFailedException($envelope, [$rootCause]);
        yield 'nested HFE recurses to the leaf root cause' => [
            new HandlerFailedException($envelope, [$innerWrapper]),
            $rootCause,
        ];

        // DelayedMessageHandlingException uses the same WrappedExceptionsTrait, so the
        // unwrapper handles it identically to HandlerFailedException.
        $delayedCause = new RuntimeException('delayed');
        yield 'DelayedMessageHandlingException returns wrapped cause' => [
            new DelayedMessageHandlingException([$delayedCause], $envelope),
            $delayedCause,
        ];
    }
}
