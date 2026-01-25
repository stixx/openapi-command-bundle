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
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed as OpenApiValidationFailed;
use Nelmio\ApiDocBundle\Exception\RenderInvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Exception\DefaultExceptionToApiProblemTransformer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class DefaultExceptionToApiProblemTransformerTest extends TestCase
{
    private DefaultExceptionToApiProblemTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new DefaultExceptionToApiProblemTransformer();
    }

    /**
     * @param array<int, array<string, string|null>>|null $expectedViolations
     */
    #[DataProvider('exceptionProvider')]
    public function testTransform(Throwable $exception, int $expectedStatus, string $expectedTitle, ?string $expectedDetail = null, ?array $expectedViolations = null): void
    {
        // Act
        $apiProblem = $this->transformer->transform($exception);

        // Assert
        self::assertSame($expectedStatus, $apiProblem->getStatusCode());
        self::assertSame($expectedTitle, $apiProblem->getTitle());
        if ($expectedDetail !== null) {
            self::assertSame($expectedDetail, $apiProblem->getDetail());
        }
        if ($expectedViolations !== null) {
            self::assertSame($expectedViolations, $apiProblem->getViolations());
        }
    }

    /**
     * @return iterable<string, array{0: Throwable, 1: int, 2: string, 3?: string|null, 4?: array<int, array<string, string|null>>|null}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'AccessDeniedHttpException' => [
            new AccessDeniedHttpException('Forbidden message'),
            Response::HTTP_FORBIDDEN,
            'Forbidden',
            'You are not allowed to perform this action.',
        ];

        yield 'NotFoundHttpException' => [
            new NotFoundHttpException('Not found message'),
            Response::HTTP_NOT_FOUND,
            'An error occurred.',
            'The requested resource could not be found.',
        ];

        $openApiValidationFailed = new OpenApiValidationFailed('OpenAPI validation failed');
        yield 'OpenApiValidationFailed' => [
            $openApiValidationFailed,
            Response::HTTP_BAD_REQUEST,
            'The request body contains errors',
            'OpenAPI validation failed',
            [[
                'constraint' => 'openapi_request_validation',
                'message' => 'OpenAPI validation failed',
            ]],
        ];

        $renderInvalidArgumentException = new RenderInvalidArgumentException('Invalid argument');
        yield 'RenderInvalidArgumentException' => [
            $renderInvalidArgumentException,
            Response::HTTP_BAD_REQUEST,
            'The request body contains errors',
            'Invalid argument',
            [[
                'constraint' => 'invalid_argument',
                'message' => 'Invalid argument',
            ]],
        ];

        yield 'HttpExceptionInterface - 400' => [
            new HttpException(Response::HTTP_BAD_REQUEST, 'Custom bad request'),
            Response::HTTP_BAD_REQUEST,
            'Bad Request',
            'Custom bad request',
        ];

        yield 'HttpExceptionInterface - 401' => [
            new HttpException(Response::HTTP_UNAUTHORIZED, 'Custom unauthorized'),
            Response::HTTP_UNAUTHORIZED,
            'Unauthorized',
            'Custom unauthorized',
        ];

        yield 'HttpExceptionInterface - 403' => [
            new HttpException(Response::HTTP_FORBIDDEN, 'Custom forbidden'),
            Response::HTTP_FORBIDDEN,
            'Forbidden',
            'Custom forbidden',
        ];

        yield 'HttpExceptionInterface - 500' => [
            new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Custom server error'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'An error occurred.',
            'Custom server error',
        ];

        yield 'Default case (generic Exception)' => [
            new Exception('Something went wrong'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'An error occurred.',
            'Something went wrong',
        ];
    }
}
