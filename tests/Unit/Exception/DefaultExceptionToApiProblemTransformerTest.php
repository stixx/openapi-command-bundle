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

use Exception;
use League\OpenAPIValidation\PSR7\Exception\NoOperation;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed as OpenApiValidationFailed;
use Nelmio\ApiDocBundle\Exception\RenderInvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Exception\DefaultExceptionToApiProblemTransformer;
use Stixx\OpenApiCommandBundle\Model\ProblemDetailsInvalidRequestBody;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Throwable;

final class DefaultExceptionToApiProblemTransformerTest extends TestCase
{
    #[DataProvider('exceptionProvider')]
    public function testTransform(Throwable $exception, int $status, string $title, ?string $detail): void
    {
        // Arrange
        $transformer = new DefaultExceptionToApiProblemTransformer();

        // Act
        $problem = $transformer->transform($exception)->getProblem();

        // Assert
        self::assertSame($status, $problem->status);
        self::assertSame($title, $problem->title);
        self::assertSame($detail, $problem->detail);
    }

    /**
     * @return iterable<string, array{Throwable, int, string, string|null}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'authentication' => [new AuthenticationException('Invalid JWT'), 401, 'Unauthorized', 'The authentication token is missing or invalid.'];
        yield 'access denied (HttpKernel)' => [new AccessDeniedHttpException('Forbidden message'), 403, 'Forbidden', 'You are not allowed to perform this action.'];
        yield 'access denied (Security)' => [new AccessDeniedException('Missing permission: manage_roles'), 403, 'Forbidden', 'You are not allowed to perform this action.'];
        yield 'not found' => [new NotFoundHttpException('No route found for "GET /api/x"'), 404, 'Not Found', 'The requested resource could not be found.'];
        yield 'OpenAPI validation' => [new OpenApiValidationFailed('Body does not match schema'), 400, 'Bad Request', 'The request does not match the API specification.'];
        yield 'Nelmio render argument' => [new RenderInvalidArgumentException('Invalid argument'), 400, 'Bad Request', null];
        yield 'HTTP exception' => [new HttpException(409, 'App\Entity\Order object not found'), 409, 'Conflict', null];
        yield 'operation missing from the OpenAPI document' => [NoOperation::fromPathAndMethod('/api/books', 'head'), 400, 'Bad Request', 'The request does not match the API specification.'];
        yield 'redirect' => [new HttpException(302, 'Moved', headers: ['Location' => '/elsewhere']), 500, 'Internal Server Error', null];
        yield 'generic exception' => [new Exception('SQLSTATE[23000]'), 500, 'Internal Server Error', null];
    }

    public function testShowsTheExceptionMessageInDebug(): void
    {
        // Arrange
        $transformer = new DefaultExceptionToApiProblemTransformer(debug: true);

        // Act
        $problem = $transformer->transform(new Exception('SQLSTATE[23000]'))->getProblem();

        // Assert
        self::assertSame('SQLSTATE[23000]', $problem->detail);
    }

    public function testKeepsTheHeadersOfAnHttpException(): void
    {
        // Arrange
        $transformer = new DefaultExceptionToApiProblemTransformer();

        // Act
        $exception = $transformer->transform(new MethodNotAllowedHttpException(['GET', 'POST']));

        // Assert
        self::assertSame(405, $exception->getStatusCode());
        self::assertSame(['Allow' => 'GET, POST'], $exception->getHeaders());
    }

    public function testDescribesAnOpenApiValidationFailureAsAViolation(): void
    {
        // Arrange
        $transformer = new DefaultExceptionToApiProblemTransformer();

        // Act
        $problem = $transformer->transform(new OpenApiValidationFailed('Body does not match schema'))->getProblem();

        // Assert
        self::assertInstanceOf(ProblemDetailsInvalidRequestBody::class, $problem);
        self::assertEquals([new Violation('', 'Body does not match schema', null, 'openapi_request_validation')], $problem->violations);
    }
}
