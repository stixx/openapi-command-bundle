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
use RuntimeException;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Model\ProblemDetails;
use Stixx\OpenApiCommandBundle\Model\ProblemDetailsInvalidRequestBody;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ApiProblemExceptionTest extends TestCase
{
    public function testTakesItsStatusFromTheProblem(): void
    {
        // Arrange
        $problem = new ProblemDetails(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Unavailable', 'Try again later');
        $headers = ['Retry-After' => '120'];

        // Act
        $exception = new ApiProblemException($problem, $headers);

        // Assert
        self::assertSame($problem, $exception->getProblem());
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getStatusCode());
        self::assertSame($headers, $exception->getHeaders());
        self::assertSame('Try again later', $exception->getMessage());
    }

    public function testTitlesAProblemForAnyStatusWithItsReasonPhrase(): void
    {
        // Arrange
        $previous = new RuntimeException('upstream down');

        // Act
        $exception = ApiProblemException::fromStatus(Response::HTTP_SERVICE_UNAVAILABLE, headers: ['Retry-After' => '120'], previous: $previous);

        // Assert
        self::assertSame('Service Unavailable', $exception->getProblem()->title);
        self::assertSame('Service Unavailable', $exception->getMessage());
        self::assertSame(['Retry-After' => '120'], $exception->getHeaders());
        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @param callable(): ApiProblemException $factory
     */
    #[DataProvider('factoryProvider')]
    public function testFactoriesTitleTheProblemWithTheReasonPhraseOfItsStatus(callable $factory, int $status, string $title, ?string $detail): void
    {
        // Act
        $problem = $factory()->getProblem();

        // Assert
        self::assertSame($status, $problem->status);
        self::assertSame($title, $problem->title);
        self::assertSame('about:blank', $problem->type);
        self::assertSame($detail, $problem->detail);
    }

    /**
     * @return iterable<string, array{callable(): ApiProblemException, int, string, string|null}>
     */
    public static function factoryProvider(): iterable
    {
        yield 'unauthenticated' => [ApiProblemException::unauthenticated(...), 401, 'Unauthorized', 'The authentication token is missing or invalid.'];
        yield 'forbidden' => [static fn () => ApiProblemException::forbidden('nope'), 403, 'Forbidden', 'nope'];
        yield 'not found' => [ApiProblemException::notFound(...), 404, 'Not Found', 'The requested resource could not be found.'];
        yield 'conflict' => [ApiProblemException::conflict(...), 409, 'Conflict', null];
        yield 'bad request' => [ApiProblemException::badRequest(...), 400, 'Bad Request', null];
        yield 'unprocessable entity' => [ApiProblemException::unprocessableEntity(...), 422, 'Unprocessable Content', null];
        yield 'server error' => [static fn () => ApiProblemException::serverError('boom'), 500, 'Internal Server Error', 'boom'];
    }

    public function testCarriesTheViolationsOfABadRequest(): void
    {
        // Arrange
        $violations = [new Violation('title', 'This value should not be blank.')];

        // Act
        $problem = ApiProblemException::badRequest('bad body', $violations)->getProblem();

        // Assert
        self::assertInstanceOf(ProblemDetailsInvalidRequestBody::class, $problem);
        self::assertSame($violations, $problem->violations);
        self::assertSame('bad body', $problem->detail);
    }

    public function testMapsAConstraintViolationListOfAnUnprocessableEntity(): void
    {
        // Arrange
        $list = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'title', '', null, NotBlank::IS_BLANK_ERROR, new NotBlank()),
        ]);

        // Act
        $problem = ApiProblemException::unprocessableEntity('Validation failed', $list)->getProblem();

        // Assert
        self::assertInstanceOf(ProblemDetailsInvalidRequestBody::class, $problem);
        self::assertEquals([new Violation('title', 'This value should not be blank.', NotBlank::IS_BLANK_ERROR, 'NotBlank', 'IS_BLANK_ERROR')], $problem->violations);
    }

    public function testLeavesTheViolationsOutWhenThereAreNone(): void
    {
        // Act
        $problem = ApiProblemException::unprocessableEntity('Validation failed', new ConstraintViolationList())->getProblem();

        // Assert
        self::assertNotInstanceOf(ProblemDetailsInvalidRequestBody::class, $problem);
    }
}
