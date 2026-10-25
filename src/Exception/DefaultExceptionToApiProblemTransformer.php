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

namespace Stixx\OpenApiCommandBundle\Exception;

use League\OpenAPIValidation\PSR7\Exception\MultipleOperationsMismatchForRequest;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed as OpenApiValidationFailed;
use Nelmio\ApiDocBundle\Exception\RenderInvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Exception messages can reveal routes, classes and data, so outside debug they never reach the response.
 * A request the OpenAPI document has no operation for passed the router, so the document is at fault, not the client.
 *
 * @internal
 */
final readonly class DefaultExceptionToApiProblemTransformer implements ExceptionToApiProblemTransformerInterface
{
    public function __construct(private bool $debug = false)
    {
    }

    public function transform(Throwable $throwable): ApiProblemException
    {
        $authenticationExceptionClass = 'Symfony\\Component\\Security\\Core\\Exception\\AuthenticationException';
        $accessDeniedExceptionClass = 'Symfony\\Component\\Security\\Core\\Exception\\AccessDeniedException';

        return match (true) {
            (class_exists($authenticationExceptionClass) && is_a($throwable, $authenticationExceptionClass)) => ApiProblemException::unauthenticated(),
            (class_exists($accessDeniedExceptionClass) && is_a($throwable, $accessDeniedExceptionClass)) => ApiProblemException::forbidden(),
            $throwable instanceof AccessDeniedHttpException => ApiProblemException::forbidden(),
            $throwable instanceof NotFoundHttpException => ApiProblemException::notFound(),
            $throwable instanceof NoPath, $throwable instanceof MultipleOperationsMismatchForRequest => ApiProblemException::serverError(
                detail: $this->messageOf($throwable),
            ),
            $throwable instanceof OpenApiValidationFailed => ApiProblemException::badRequest(
                detail: 'The request does not match the API specification.',
                violations: [ViolationMapper::fromOpenApiValidation($throwable)],
            ),
            $throwable instanceof RenderInvalidArgumentException => ApiProblemException::badRequest(
                detail: $this->messageOf($throwable),
            ),
            $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() >= 400 => ApiProblemException::fromStatus(
                $throwable->getStatusCode(),
                $this->messageOf($throwable),
                // HttpExceptionInterface::getHeaders() is typed as plain `array`.
                /* @phpstan-ignore-next-line */
                $throwable->getHeaders(),
                $throwable,
            ),
            default => ApiProblemException::serverError(detail: $this->messageOf($throwable)),
        };
    }

    private function messageOf(Throwable $throwable): ?string
    {
        return $this->debug && $throwable->getMessage() !== '' ? $throwable->getMessage() : null;
    }
}
