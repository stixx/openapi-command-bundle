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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Response\StatusResolverInterface;
use Stixx\OpenApiCommandBundle\Tests\Mock\Command\ExampleCommand;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CommandControllerTest extends TestCase
{
    public function testInvokeValidatesDispatchesAndReturnsJsonResponse(): void
    {
        // Arrange
        $command = new ExampleCommand();
        $request = new Request();

        $validator = $this->createMock(ValidatorInterface::class);
        $violations = $this->createMock(ConstraintViolationListInterface::class);

        $violations->method('count')->willReturn(0);
        $validator->expects(self::once())
            ->method('validate')
            ->with($command, null, ['Default'])
            ->willReturn($violations);

        $result = ['ok' => true];
        $envelope = new Envelope($command, [new HandledStamp($result, 'handler')]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willReturn($envelope);

        $statusResolver = $this->createMock(StatusResolverInterface::class);
        $statusResolver->expects(self::once())
            ->method('resolve')
            ->with($request, $command)
            ->willReturn(201);

        $responder = $this->createMock(ResponderInterface::class);
        $responder->expects(self::once())
            ->method('respond')
            ->with($result, 201)
            ->willReturn(new Response(json_encode($result), 201, ['Content-Type' => 'application/json']));

        // Act
        $controller = new CommandController($messageBus, $validator, $statusResolver, $responder);
        $response = $controller($request, $command);

        // Assert
        self::assertSame(201, $response->getStatusCode());
        self::assertSame(json_encode($result), $response->getContent());
        self::assertSame('application/json', $response->headers->get('content-type'));
    }

    public function testInvokeSkipsValidationWhenDisabled(): void
    {
        // Arrange
        $command = new ExampleCommand();
        $request = new Request();

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())
            ->method('validate');

        $result = ['done' => 1];
        $envelope = new Envelope($command, [new HandledStamp($result, 'handler')]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->with($command)
            ->willReturn($envelope);

        $statusResolver = $this->createMock(StatusResolverInterface::class);
        $statusResolver->method('resolve')
            ->with($request, $command)
            ->willReturn(200);

        $responder = $this->createMock(ResponderInterface::class);
        $responder->method('respond')
            ->with($result, 200)
            ->willReturn(new Response(json_encode($result), 200));

        // Act
        $controller = new CommandController($messageBus, $validator, $statusResolver, $responder, validationEnabled: false);
        $response = $controller($request, $command);

        // Assert
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(json_encode($result), $response->getContent());
    }

    public function testInvokeThrowsApiProblemExceptionWhenValidationFails(): void
    {
        // Arrange
        $this->expectException(ApiProblemException::class);

        $command = new ExampleCommand();
        $request = new Request();

        $violations = $this->createMock(ConstraintViolationListInterface::class);
        $violations->method('count')
            ->willReturn(2);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')
            ->willReturn($violations);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())
            ->method('dispatch');

        $statusResolver = $this->createMock(StatusResolverInterface::class);
        $statusResolver->expects(self::never())
            ->method('resolve');

        $responder = $this->createMock(ResponderInterface::class);

        // Act
        $controller = new CommandController($messageBus, $validator, $statusResolver, $responder, validationEnabled: true);
        $controller($request, $command);
    }

    public function testInvokeRethrowsPreviousExceptionFromHandlerFailedException(): void
    {
        // Arrange
        $command = new ExampleCommand();
        $request = new Request();

        $validator = $this->createMock(ValidatorInterface::class);
        $violations = $this->createMock(ConstraintViolationListInterface::class);

        $violations->method('count')
            ->willReturn(0);
        $validator->method('validate')
            ->willReturn($violations);

        $exception = new HandlerFailedException(
            new Envelope($command),
            [new RuntimeException('boom')]
        );

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willThrowException($exception);

        $statusResolver = $this->createMock(StatusResolverInterface::class);
        $responder = $this->createMock(ResponderInterface::class);

        // Act
        $controller = new CommandController($messageBus, $validator, $statusResolver, $responder, validationEnabled: true);

        // Assert
        try {
            $controller($request, $command);
            self::fail('Expected RuntimeException to be thrown');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }
    }
}
