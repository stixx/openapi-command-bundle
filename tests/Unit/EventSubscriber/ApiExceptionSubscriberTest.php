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
use Stixx\OpenApiCommandBundle\EventSubscriber\ApiExceptionSubscriber;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

class ApiExceptionSubscriberTest extends AbstractEventSubscriberTestCase
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
        $subscriber = new ApiExceptionSubscriber($routes, $normalizer);

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
            ->with($violations, 'json')
            ->willReturn($violations);

        $subscriber = new ApiExceptionSubscriber($routes, $normalizer);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $data = json_decode((string) $response->getContent(), true);
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

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->expects(self::never())->method('normalize');

        $subscriber = new ApiExceptionSubscriber($routes, $normalizer);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame($expectedStatus, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame($expectedTitle, $data['title']);
        self::assertSame($expectedStatus, $data['status']);
        self::assertSame('about:blank', $data['type']);
    }

    public static function exceptionMappingProvider(): iterable
    {
        yield 'forbidden from access denied' => [new AccessDeniedHttpException(), 'api_forbidden', 403, 'Forbidden'];
        yield 'server error from generic exception' => [new RuntimeException('oops'), 'api_error', 500, 'An error occurred.'];
    }
}
