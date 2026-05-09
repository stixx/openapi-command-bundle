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

use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Exception\ExceptionToApiProblemTransformerInterface;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NelmioAreaRoutesChecker $nelmioAreaRoutesChecker,
        private NormalizerInterface $normalizer,
        private ExceptionToApiProblemTransformerInterface $exceptionTransformer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->nelmioAreaRoutesChecker->isApiRoute($event->getRequest())) {
            return;
        }

        try {
            $response = $this->buildProblemResponse($event->getThrowable());
        } catch (Throwable) {
            $response = $this->buildFallbackResponse();
        }

        $event->setResponse($response);
        $event->stopPropagation();
    }

    private function buildProblemResponse(Throwable $throwable): JsonResponse
    {
        // Unwrap HandlerFailedException so the actual cause can be transformed correctly.
        // Messenger wraps handler and middleware exceptions in HandlerFailedException; without
        // unwrapping, all handler errors fall through to the default 500 case.
        if ($throwable instanceof HandlerFailedException) {
            $throwable = array_values($throwable->getWrappedExceptions())[0] ?? $throwable;
        }

        if (!$throwable instanceof ApiProblemException) {
            $throwable = $this->exceptionTransformer->transform($throwable);
        }

        $payload = $this->normalizer->normalize($throwable, JsonEncoder::FORMAT);

        return new JsonResponse($payload, $throwable->getStatusCode(), array_merge([
            'Content-Type' => 'application/problem+json',
        ], $throwable->getHeaders()));
    }

    private function buildFallbackResponse(): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => 'An error occurred.',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
