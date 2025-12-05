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
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NelmioAreaRoutes $nelmioAreaRoutes,
        private SerializerInterface $serializer,
        private ExceptionToApiProblemTransformerInterface $exceptionTransformer,
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
            $throwable = $this->exceptionTransformer->transform($throwable);
        }

        $payload = $this->serializer->normalize($throwable, 'json');

        $response = new JsonResponse($payload, $throwable->getStatusCode(), array_merge([
            'Content-Type' => 'application/problem+json',
        ], $throwable->getHeaders()));

        $event->setResponse($response);
        $event->stopPropagation();
    }
}
