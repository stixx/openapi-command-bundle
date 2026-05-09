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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use Stixx\OpenApiCommandBundle\EventSubscriber\ApiExceptionSubscriber;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface;
use Stixx\OpenApiCommandBundle\Exception\WrappedExceptionUnwrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

final class ApiExceptionSubscriberTest extends AbstractEventSubscriberTestCase
{
    #[DataProvider('skipRequestProvider')]
    public function testSkipsHandlingForNonApplicableRequests(int $requestType, string $areaRoute, string $requestRoute): void
    {
        // Arrange
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request();
        $throwable = new RuntimeException('boom');
        $event = new ExceptionEvent($kernel, $request, $requestType, $throwable);

        $routes = $this->createNelmioAreaRoutesWithRouteName($areaRoute);
        $request->attributes->set('_route', $requestRoute);

        $normalizer = $this->createMock(NormalizerInterface::class);
        $transformer = $this->createMock(ExceptionToApiProblemTransformerInterface::class);
        $subscriber = new ApiExceptionSubscriber($routes, $normalizer, $transformer, new WrappedExceptionUnwrapper());

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertFalse($event->isPropagationStopped());
        self::assertNull($event->getResponse());
    }

    public function testUsesApiProblemExceptionAsIsAndBuildsProblemJsonResponse(): void
    {
        // Arrange
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request();

        $violations = [
            ['constraint' => 'x', 'message' => 'y'],
        ];
        $apiProblem = ApiProblemException::badRequest(
            detail: 'bad',
            violations: $violations
        );

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $apiProblem);

        $routes = $this->createNelmioAreaRoutesWithRouteName('api_problem');
        $request->attributes->set('_route', 'api_problem');

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->expects(self::once())
            ->method('normalize')
            ->willReturn(['detail' => 'bad', 'violations' => $violations, 'status' => 400, 'title' => 'The request body contains errors', 'type' => 'about:blank']);

        $transformer = $this->createMock(ExceptionToApiProblemTransformerInterface::class);
        $subscriber = new ApiExceptionSubscriber($routes, $normalizer, $transformer, new WrappedExceptionUnwrapper());

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        self::assertIsString($content);
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);
        self::assertSame('about:blank', $data['type']);
        self::assertSame('The request body contains errors', $data['title']);
        self::assertSame(400, $data['status']);
        self::assertSame('bad', $data['detail']);
        self::assertSame($violations, $data['violations']);
    }

    #[DataProvider('exceptionMappingProvider')]
    public function testMapsExceptionToApiProblem(Throwable $throwable, string $routeName, int $expectedStatus, string $expectedTitle): void
    {
        // Arrange
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request();
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);

        $routes = $this->createNelmioAreaRoutesWithRouteName($routeName);
        $request->attributes->set('_route', $routeName);

        $apiProblem = new ApiProblemException($expectedStatus, $expectedTitle);

        $transformer = $this->createMock(ExceptionToApiProblemTransformerInterface::class);
        $transformer->expects(self::once())
            ->method('transform')
            ->with($throwable)
            ->willReturn($apiProblem);

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->expects(self::once())
            ->method('normalize')
            ->with($apiProblem, 'json')
            ->willReturn(['title' => $expectedTitle, 'status' => $expectedStatus, 'type' => 'about:blank']);

        $subscriber = new ApiExceptionSubscriber($routes, $normalizer, $transformer, new WrappedExceptionUnwrapper());

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame($expectedStatus, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);
        self::assertSame($expectedTitle, $data['title']);
        self::assertSame($expectedStatus, $data['status']);
        self::assertSame('about:blank', $data['type']);
    }

    public function testFallsBackToProblemJsonWhenNormalizerThrows(): void
    {
        // Arrange
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request();
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('boom'));

        $routes = $this->createNelmioAreaRoutesWithRouteName('api_route');
        $request->attributes->set('_route', 'api_route');

        $transformer = $this->createMock(ExceptionToApiProblemTransformerInterface::class);
        $transformer->method('transform')->willReturn(ApiProblemException::serverError());

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('normalize')->willThrowException(new RuntimeException('normalizer exploded'));

        $subscriber = new ApiExceptionSubscriber($routes, $normalizer, $transformer, new WrappedExceptionUnwrapper());

        // Act
        $subscriber->onKernelException($event);

        // Assert: a normalizer failure must not leak HTML; a static problem+json 500 is returned instead.
        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        self::assertIsString($content);
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true);
        self::assertSame('about:blank', $data['type']);
        self::assertSame('An error occurred.', $data['title']);
        self::assertSame(500, $data['status']);
    }

    public function testUnwrapsHandlerFailedExceptionBeforeTransforming(): void
    {
        // Arrange
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request();
        $cause = new RuntimeException('the real cause');
        $handlerFailed = new HandlerFailedException(new Envelope(new stdClass()), [$cause]);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $handlerFailed);

        $routes = $this->createNelmioAreaRoutesWithRouteName('api_route');
        $request->attributes->set('_route', 'api_route');

        $apiProblem = ApiProblemException::serverError(detail: 'the real cause');

        $transformer = $this->createMock(ExceptionToApiProblemTransformerInterface::class);
        $transformer->expects(self::once())
            ->method('transform')
            ->with($cause) // receives the unwrapped cause, not the HandlerFailedException
            ->willReturn($apiProblem);

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('normalize')->willReturn(['status' => 500, 'title' => 'An error occurred.', 'type' => 'about:blank']);

        $subscriber = new ApiExceptionSubscriber($routes, $normalizer, $transformer, new WrappedExceptionUnwrapper());

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertTrue($event->isPropagationStopped());
        self::assertSame(500, $event->getResponse()?->getStatusCode());
    }

    /**
     * @return iterable<string, array{0: Throwable, 1: string, 2: int, 3: string}>
     */
    public static function exceptionMappingProvider(): iterable
    {
        yield 'forbidden from access denied' => [new AccessDeniedHttpException(), 'api_forbidden', 403, 'Forbidden'];
        yield 'server error from generic exception' => [new RuntimeException('oops'), 'api_error', 500, 'An error occurred.'];
    }
}
