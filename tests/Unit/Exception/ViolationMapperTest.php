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

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Exception\ViolationMapper;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ViolationMapperTest extends TestCase
{
    private const string SPEC = <<<'JSON'
        {
          "openapi": "3.0.0",
          "info": {"title": "test", "version": "1"},
          "paths": {
            "/books": {
              "post": {
                "parameters": [
                  {"name": "limit", "in": "query", "required": true, "schema": {"type": "integer"}},
                  {"name": "X-Trace", "in": "header", "schema": {"type": "integer"}}
                ],
                "requestBody": {"required": true, "content": {"application/json": {"schema": {
                  "type": "object",
                  "required": ["title"],
                  "properties": {
                    "title": {"type": "string"},
                    "authors": {"type": "array", "items": {"type": "object", "properties": {"name": {"type": "string"}}}}
                  }
                }}}},
                "responses": {"201": {"description": "created"}}
              }
            }
          }
        }
        JSON;

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('openApiFailureProvider')]
    public function testPointsAnOpenApiViolationAtTheInvalidValue(string $query, array $headers, string $body, Violation $expected): void
    {
        // Arrange
        $failure = $this->validationFailureFor($query, $headers, $body);

        // Act
        $violation = ViolationMapper::fromOpenApiValidation($failure);

        // Assert
        self::assertEquals($expected, $violation);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string, Violation}>
     */
    public static function openApiFailureProvider(): iterable
    {
        $violation = static fn (string $propertyPath, string $message, ?string $code = null): Violation => new Violation($propertyPath, $message, $code, 'openapi_request_validation');

        yield 'missing query parameter' => ['', [], '{"title": "Dune"}', $violation('limit', 'Missing required argument "limit" for Request [post /books]')];
        yield 'invalid query parameter' => ['limit=many', [], '{"title": "Dune"}', $violation('limit', 'Value expected to be \'integer\', but \'string\' given.', 'type')];
        yield 'invalid header' => ['limit=1', ['X-Trace' => 'abc'], '{"title": "Dune"}', $violation('', 'Value "abc" for header "X-Trace" is invalid for Request [post /books]: Value expected to be \'integer\', but \'string\' given.', 'type')];
        yield 'missing body property' => ['limit=1', [], '{}', $violation('title', 'Keyword validation failed: Required property \'title\' must be present in the object', 'required')];
        yield 'invalid body property' => ['limit=1', [], '{"title": 42}', $violation('title', 'Value expected to be \'string\', but \'integer\' given.', 'type')];
        yield 'invalid nested body property' => ['limit=1', [], '{"title": "Dune", "authors": [{"name": 1}]}', $violation('authors[0].name', 'Value expected to be \'string\', but \'integer\' given.', 'type')];
        yield 'invalid JSON' => ['limit=1', [], '{"title":', $violation('', 'JSON parsing failed with "Syntax error" for Request [post /books]')];
    }

    public function testMapsAConstraintViolationWithItsConstraintAndErrorName(): void
    {
        // Arrange
        $list = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'title', '', null, NotBlank::IS_BLANK_ERROR, new NotBlank()),
            new ConstraintViolation('The command is invalid.', null, [], null, '', null),
        ]);

        // Act
        $violations = ViolationMapper::fromConstraintViolations($list);

        // Assert
        self::assertEquals([
            new Violation('title', 'This value should not be blank.', NotBlank::IS_BLANK_ERROR, 'NotBlank', 'IS_BLANK_ERROR'),
            new Violation('', 'The command is invalid.'),
        ], $violations);
    }

    /**
     * @param array<string, string> $headers
     */
    private function validationFailureFor(string $query, array $headers, string $body): ValidationFailed
    {
        $validator = new ValidatorBuilder()->fromJson(self::SPEC)->getRequestValidator();
        $request = new ServerRequest('POST', '/books?'.$query, $headers + ['Content-Type' => 'application/json'], $body);
        parse_str($query, $queryParams);

        try {
            $validator->validate($request->withQueryParams($queryParams));
        } catch (ValidationFailed $failure) {
            return $failure;
        }

        self::fail('The request was expected to fail validation.');
    }
}
