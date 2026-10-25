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

use RuntimeException;
use Stixx\OpenApiCommandBundle\Model\ProblemDetails;
use Stixx\OpenApiCommandBundle\Model\ProblemDetailsInvalidRequestBody;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

/**
 * @api
 */
final class ApiProblemException extends RuntimeException implements HttpExceptionInterface
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        private readonly ProblemDetails $problem,
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($problem->detail ?? $problem->title, 0, $previous);
    }

    public function getProblem(): ProblemDetails
    {
        return $this->problem;
    }

    public function getStatusCode(): int
    {
        return $this->problem->status;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Titles the problem with the reason phrase of the status.
     *
     * @param array<string, string|list<string>> $headers
     */
    public static function fromStatus(int $status, ?string $detail = null, array $headers = [], ?Throwable $previous = null): self
    {
        return new self(new ProblemDetails($status, self::titleFor($status), $detail), $headers, $previous);
    }

    public static function unauthenticated(?string $detail = 'The authentication token is missing or invalid.'): self
    {
        return self::fromStatus(Response::HTTP_UNAUTHORIZED, $detail);
    }

    public static function forbidden(?string $detail = 'You are not allowed to perform this action.'): self
    {
        return self::fromStatus(Response::HTTP_FORBIDDEN, $detail);
    }

    public static function notFound(?string $detail = 'The requested resource could not be found.'): self
    {
        return self::fromStatus(Response::HTTP_NOT_FOUND, $detail);
    }

    public static function conflict(?string $detail = null): self
    {
        return self::fromStatus(Response::HTTP_CONFLICT, $detail);
    }

    /**
     * @param list<Violation>|ConstraintViolationListInterface $violations
     */
    public static function badRequest(?string $detail = null, array|ConstraintViolationListInterface $violations = []): self
    {
        return self::withViolations(Response::HTTP_BAD_REQUEST, $detail, $violations);
    }

    /**
     * @param list<Violation>|ConstraintViolationListInterface $violations
     */
    public static function unprocessableEntity(?string $detail = null, array|ConstraintViolationListInterface $violations = []): self
    {
        return self::withViolations(Response::HTTP_UNPROCESSABLE_ENTITY, $detail, $violations);
    }

    public static function serverError(?string $detail = null): self
    {
        return self::fromStatus(Response::HTTP_INTERNAL_SERVER_ERROR, $detail);
    }

    /**
     * @param list<Violation>|ConstraintViolationListInterface $violations
     */
    private static function withViolations(int $status, ?string $detail, array|ConstraintViolationListInterface $violations): self
    {
        if ($violations instanceof ConstraintViolationListInterface) {
            $violations = ViolationMapper::fromConstraintViolations($violations);
        }

        if ($violations === []) {
            return self::fromStatus($status, $detail);
        }

        return new self(new ProblemDetailsInvalidRequestBody($status, self::titleFor($status), $violations, $detail));
    }

    private static function titleFor(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'Unknown Status';
    }
}
