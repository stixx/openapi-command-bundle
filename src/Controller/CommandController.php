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

namespace Stixx\OpenApiCommandBundle\Controller;

use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Response\StatusResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

#[AsController]
final readonly class CommandController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private ValidatorInterface $validator,
        private StatusResolverInterface $statusResolver,
        private bool $validateHttp = true,
        /** @var string[] */
        private array $validationGroups = ['Default']
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    public function __invoke(Request $request, #[CommandObject] object $command): JsonResponse
    {
        if ($this->validateHttp) {
            $this->validateCommand($command);
        }

        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $previousException = $exception->getPrevious();

            throw $previousException ?? $exception;
        }

        $handled = $envelope->last(HandledStamp::class);
        $result = $handled?->getResult();

        return new JsonResponse($result, $this->statusResolver->resolve($request, $command));
    }

    private function validateCommand(object $command): void
    {
        $violations = $this->validator->validate(value: $command, groups: $this->validationGroups);
        if ($violations->count() > 0) {
            throw ApiProblemException::badRequest(
                detail: 'Validation failed',
                violations: $violations
            );
        }
    }
}
