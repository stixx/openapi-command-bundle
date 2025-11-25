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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

final class ApiProblemException extends RuntimeException implements HttpExceptionInterface
{
    /**
     * @param array<int, array<string, string|null>>|ConstraintViolationListInterface|null $violations
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $title = 'An error occurred.',
        private readonly string $type = 'about:blank',
        private readonly ?string $detail = null,
        private readonly ?string $instance = null,
        private readonly array|ConstraintViolationListInterface|null $violations = null,
        ?Throwable $previous = null,
        private readonly array $headers = []
    ) {
        parent::__construct($detail ?? $title, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getInstance(): ?string
    {
        return $this->instance;
    }

    /**
     * @return array<int, array<string, string|null>>|ConstraintViolationListInterface|null
     */
    public function getViolations(): array|ConstraintViolationListInterface|null
    {
        return $this->violations;
    }

    public static function unauthenticated(?string $detail = 'The authentication token is missing or invalid.'): self
    {
        return new self(
            statusCode: Response::HTTP_UNAUTHORIZED,
            title: 'Unauthenticated',
            type: 'about:blank',
            detail: $detail
        );
    }

    public static function forbidden(?string $detail = 'You are not allowed to perform this action.'): self
    {
        return new self(
            statusCode: Response::HTTP_FORBIDDEN,
            title: 'Forbidden',
            type: 'about:blank',
            detail: $detail
        );
    }

    public static function notFound(?string $detail = 'The requested resource could not be found.'): self
    {
        return new self(
            statusCode: Response::HTTP_NOT_FOUND,
            title: 'An error occurred.',
            type: 'about:blank',
            detail: $detail
        );
    }

    /**
     * @param array<int, array<string, string|null>>|ConstraintViolationListInterface $violations
     */
    public static function badRequest(string $title = 'The request body contains errors', ?string $detail = null, array|ConstraintViolationListInterface $violations = []): self
    {
        return new self(
            statusCode: Response::HTTP_BAD_REQUEST,
            title: $title,
            type: 'about:blank',
            detail: $detail,
            violations: $violations
        );
    }

    public static function serverError(?string $detail = null): self
    {
        return new self(
            statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            title: 'An error occurred.',
            type: 'about:blank',
            detail: $detail
        );
    }
}
