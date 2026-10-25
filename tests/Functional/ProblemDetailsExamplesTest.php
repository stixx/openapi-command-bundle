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

namespace Stixx\OpenApiCommandBundle\Tests\Functional;

use Closure;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ApiProblemNormalizer;

final class ProblemDetailsExamplesTest extends AbstractKernelTestCase
{
    /**
     * @param Closure(?string, list<Violation>): ApiProblemException $factory
     */
    #[WithoutErrorHandler]
    #[DataProvider('componentProvider')]
    public function testDocumentedExampleIsWhatTheBundleSends(string $component, Closure $factory): void
    {
        // Arrange
        $example = $this->exampleOf($component);
        $detail = self::nullableString($example['detail'] ?? null);
        $documentedViolations = $example['violations'] ?? [];
        self::assertIsArray($documentedViolations);
        $violations = [];
        foreach ($documentedViolations as $violation) {
            self::assertIsArray($violation);
            self::assertIsString($violation['propertyPath'] ?? null);
            self::assertIsString($violation['message'] ?? null);
            $violations[] = new Violation(
                $violation['propertyPath'],
                $violation['message'],
                self::nullableString($violation['code'] ?? null),
                self::nullableString($violation['constraint'] ?? null),
                self::nullableString($violation['error'] ?? null),
            );
        }

        // Act
        $payload = new ApiProblemNormalizer()->normalize($factory($detail, $violations));

        // Assert
        self::assertSame($example, json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{string, Closure(?string, list<Violation>): ApiProblemException}>
     */
    public static function componentProvider(): iterable
    {
        yield 'invalid request' => ['InvalidRequestProblemDetailsResponse', ApiProblemException::badRequest(...)];
        yield 'unprocessable entity' => ['UnprocessableEntityProblemDetailsResponse', ApiProblemException::unprocessableEntity(...)];
        yield 'unauthorized' => ['UnauthorizedProblemDetailsResponse', static fn (?string $detail) => ApiProblemException::unauthenticated($detail)];
        yield 'forbidden' => ['ForbiddenProblemDetailsResponse', static fn (?string $detail) => ApiProblemException::forbidden($detail)];
        yield 'not found' => ['ResourceNotFoundProblemDetailsResponse', static fn (?string $detail) => ApiProblemException::notFound($detail)];
        yield 'conflict' => ['ConflictProblemDetailsResponse', static fn (?string $detail) => ApiProblemException::conflict($detail)];
        yield 'default' => ['DefaultProblemDetailsResponse', static fn (?string $detail) => ApiProblemException::serverError($detail)];
    }

    #[WithoutErrorHandler]
    public function testEveryDocumentedResponseHasAnExample(): void
    {
        // Act
        $components = array_keys($this->spec()['components']['responses']);

        // Assert
        self::assertEqualsCanonicalizing(array_column(iterator_to_array(self::componentProvider(), false), 0), $components);
    }

    private static function nullableString(mixed $value): ?string
    {
        self::assertTrue($value === null || is_string($value));

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleOf(string $component): array
    {
        /** @var array<string, mixed> $example */
        $example = $this->spec()['components']['responses'][$component]['content']['application/problem+json']['example'];

        return $example;
    }

    /**
     * @return array{components: array{responses: array<string, array{content: array{'application/problem+json': array{example: mixed}}}>}}
     */
    private function spec(): array
    {
        /** @var ApiDocGenerator $generator */
        $generator = self::getContainer()->get('nelmio_api_doc.generator.default');

        /** @var array{components: array{responses: array<string, array{content: array{'application/problem+json': array{example: mixed}}}>}} $spec */
        $spec = json_decode($generator->generate()->toJson(), true, 512, JSON_THROW_ON_ERROR);

        return $spec;
    }
}
