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

use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutes;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorChain;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RequestValidatorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestValidatorChain $requestValidatorChain,
        private NelmioAreaRoutes $nelmioAreaRoutes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['validateRequest', 7],
            ],
        ];
    }

    public function validateRequest(KernelEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->nelmioAreaRoutes->isApiRoute($event->getRequest())) {
            return;
        }

        $this->requestValidatorChain->validate($event->getRequest());
    }
}
