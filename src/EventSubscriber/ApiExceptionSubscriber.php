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

namespace Stixx\OpenApiCommandBundle\EventSubscriber;

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed as OpenApiValidationFailed;
use Nelmio\ApiDocBundle\Exception\RenderInvalidArgumentException;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NelmioAreaRoutes $nelmioAreaRoutes,
        private SerializerInterface $serializer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->nelmioAreaRoutes->isApiRoute($event->getRequest())) {
            return;
        }

        $throwable = $event->getThrowable();

        if (!$throwable instanceof ApiProblemException) {
            $throwable = $this->mapToApiProblem($throwable);
        }

        $payload = [
            'type' => $throwable->getType(),
            'title' => $throwable->getTitle(),
            'status' => $throwable->getStatusCode(),
        ];
        if ($throwable->getDetail() !== null) {
            $payload['detail'] = $throwable->getDetail();
        }
        if ($throwable->getInstance() !== null) {
            $payload['instance'] = $throwable->getInstance();
        }
        if ($throwable->getViolations() !== null && $throwable->getViolations() !== []) {
            $payload['violations'] = $this->serializer->normalize($throwable->getViolations(), 'json');
        }

        $response = new JsonResponse($payload, $throwable->getStatusCode(), array_merge([
            'Content-Type' => 'application/problem+json',
        ], $throwable->getHeaders()));

        $event->setResponse($response);
        $event->stopPropagation();
    }

    private function mapToApiProblem(Throwable $throwable): ApiProblemException
    {
        $authenticationExceptionClass = 'Symfony\\Component\\Security\\Core\\Exception\\AuthenticationException';

        return match (true) {
            (class_exists($authenticationExceptionClass) && is_a($throwable, $authenticationExceptionClass)) => ApiProblemException::unauthenticated(),
            $throwable instanceof AccessDeniedHttpException => ApiProblemException::forbidden(),
            $throwable instanceof NotFoundHttpException => ApiProblemException::notFound(),
            $throwable instanceof OpenApiValidationFailed => ApiProblemException::badRequest(
                detail: $throwable->getMessage(),
                violations: [[
                    'constraint' => 'openapi_request_validation',
                    'message' => $throwable->getMessage(),
                ]]
            ),
            $throwable instanceof RenderInvalidArgumentException => ApiProblemException::badRequest(
                detail: $throwable->getMessage(),
                violations: [[
                    'constraint' => 'invalid_argument',
                    'message' => $throwable->getMessage(),
                ]]
            ),
            $throwable instanceof HttpExceptionInterface => new ApiProblemException(
                statusCode: $throwable->getStatusCode(),
                title: $this->defaultTitleForStatus($throwable->getStatusCode()),
                type: 'about:blank',
                detail: $throwable->getMessage() ?: null,
                previous: $throwable,
                headers: $throwable->getHeaders()
            ),
            default => ApiProblemException::serverError(),
        };
    }

    private function defaultTitleForStatus(int $status): string
    {
        return match (true) {
            Response::HTTP_BAD_REQUEST === $status => 'Bad Request',
            Response::HTTP_UNAUTHORIZED === $status => 'Unauthorized',
            Response::HTTP_FORBIDDEN === $status => 'Forbidden',
            default => 'An error occurred.',
        };
    }
}
