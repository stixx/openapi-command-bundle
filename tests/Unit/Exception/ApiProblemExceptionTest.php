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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ApiProblemExceptionTest extends TestCase
{
    public function testConstructorSetsPropertiesAndImplementsInterface(): void
    {
        // Arrange
        $violations = [['field' => 'name', 'message' => 'Required']];
        $headers = ['X-Foo' => 'bar'];

        // Act
        $exception = new ApiProblemException(
            statusCode: 422,
            title: 'Unprocessable Entity',
            type: 'https://example.com/errors/unprocessable',
            detail: 'Invalid payload',
            instance: '/orders/123',
            violations: $violations,
            previous: null,
            headers: $headers,
        );

        // Assert
        self::assertSame(422, $exception->getStatusCode());
        self::assertSame('Unprocessable Entity', $exception->getTitle());
        self::assertSame('https://example.com/errors/unprocessable', $exception->getType());
        self::assertSame('Invalid payload', $exception->getDetail());
        self::assertSame('/orders/123', $exception->getInstance());
        self::assertSame($violations, $exception->getViolations());
        self::assertSame($headers, $exception->getHeaders());
        self::assertSame('Invalid payload', $exception->getMessage());
    }

    public function testMessageDefaultsToTitleWhenNoDetailProvided(): void
    {
        // Arrange
        $title = 'Something went wrong';

        // Act
        $exception = new ApiProblemException(statusCode: 418, title: $title);

        // Assert
        self::assertSame($title, $exception->getTitle());
        self::assertNull($exception->getDetail());
        self::assertSame($title, $exception->getMessage());
    }

    public function testHeadersAreReturned(): void
    {
        // Arrange
        $headers = ['Retry-After' => '120'];

        // Act
        $exception = new ApiProblemException(statusCode: 503, headers: $headers);

        // Assert
        self::assertSame($headers, $exception->getHeaders());
    }

    #[DataProvider('exceptionProvider')]
    public function testSimpleFactoriesCreateExpectedProblems(callable $factory, int $expectedStatus, string $expectedTitle, ?string $detail): void
    {
        // Act
        $exception = $factory($detail);

        // Assert
        self::assertSame($expectedStatus, $exception->getStatusCode());
        self::assertSame($expectedTitle, $exception->getTitle());
        self::assertSame('about:blank', $exception->getType());
        self::assertSame($detail, $exception->getDetail());

        $expectedMessage = $detail ?? $expectedTitle;
        self::assertSame($expectedMessage, $exception->getMessage());
    }

    public static function exceptionProvider(): iterable
    {
        yield 'unauthenticated default detail' => [
            static fn (?string $detail) => ApiProblemException::unauthenticated($detail),
            Response::HTTP_UNAUTHORIZED,
            'Unauthenticated',
            null,
        ];

        yield 'forbidden with custom detail' => [
            static fn (?string $detail) => ApiProblemException::forbidden($detail),
            Response::HTTP_FORBIDDEN,
            'Forbidden',
            'nope',
        ];

        yield 'not found default detail' => [
            static fn (?string $detail) => ApiProblemException::notFound($detail),
            Response::HTTP_NOT_FOUND,
            'An error occurred.',
            null,
        ];

        yield 'server error with custom detail' => [
            static fn (?string $detail) => ApiProblemException::serverError($detail),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'An error occurred.',
            'boom',
        ];
    }

    public function testBadRequestIncludesViolationsArray(): void
    {
        // Arrange
        $violations = [
            ['constraint' => 'c1', 'message' => 'm1'],
            ['constraint' => 'c2', 'message' => 'm2'],
        ];

        // Act
        $exception = ApiProblemException::badRequest(detail: 'bad body', violations: $violations);

        // Assert
        self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        self::assertSame('The request body contains errors', $exception->getTitle());
        self::assertSame('about:blank', $exception->getType());
        self::assertSame('bad body', $exception->getDetail());
        self::assertSame($violations, $exception->getViolations());
    }

    public function testBadRequestAcceptsConstraintViolationList(): void
    {
        // Arrange
        $list = $this->createMock(ConstraintViolationListInterface::class);

        // Act
        $exception = ApiProblemException::badRequest(violations: $list);

        // Assert
        self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        self::assertSame('The request body contains errors', $exception->getTitle());
        self::assertSame($list, $exception->getViolations());
    }
}
