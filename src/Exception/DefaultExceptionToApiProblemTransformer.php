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

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed as OpenApiValidationFailed;
use Nelmio\ApiDocBundle\Exception\RenderInvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class DefaultExceptionToApiProblemTransformer implements ExceptionToApiProblemTransformerInterface
{
    public function transform(Throwable $throwable): ApiProblemException
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
                detail: $throwable->getMessage() ?? null,
                previous: $throwable,
                headers: $throwable->getHeaders()
            ),
            default => ApiProblemException::serverError(
                detail: $throwable->getMessage() ?? null,
            ),
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
