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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Validator;

use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Post;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OpenApi\Context;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Validator\RequestValidator;
use Symfony\Component\HttpFoundation\Request;

final class RequestValidatorTest extends TestCase
{
    public function testValidateSuccessful(): void
    {
        // Arrange
        $describer = $this->createDescriber([]);

        $apiDocGenerator = new ApiDocGenerator([$describer], []);
        $validator = new RequestValidator($apiDocGenerator);

        $request = Request::create('/test', 'POST');

        // Act
        $validator->validate($request);

        // Assert
        $this->expectNotToPerformAssertions();
    }

    public function testValidateThrowsExceptionOnValidationError(): void
    {
        // Arrange
        $describer = $this->createDescriber([
            new Parameter([
                'name' => 'X-Required-Header',
                'in' => 'header',
                'required' => true,
                'schema' => new Schema(['type' => 'string', '_context' => new Context(['version' => '3.0.0'], null)]),
                '_context' => new Context(['version' => '3.0.0'], null),
            ]),
        ]);

        $apiDocGenerator = new ApiDocGenerator([$describer], []);
        $validator = new RequestValidator($apiDocGenerator);

        $request = Request::create('/test', 'POST');
        // The request is missing the 'X-Required-Header' defined in the OpenAPI spec above

        // Act & Assert
        $this->expectException(ValidationFailed::class);
        $validator->validate($request);
    }

    /**
     * @param array<int, Parameter> $parameters
     */
    private function createDescriber(array $parameters): DescriberInterface
    {
        return new readonly class ($parameters) implements DescriberInterface {
            /**
             * @param array<int, Parameter> $parameters
             */
            public function __construct(private array $parameters)
            {
            }

            public function describe(OpenApi $api): void
            {
                $api->openapi = '3.0.0';
                $api->info = new Info(['title' => 'Test', 'version' => '1.0.0', '_context' => new Context(['version' => '3.0.0'], null)]);
                $api->paths = [
                    new PathItem([
                        'path' => '/test',
                        'post' => new Post([
                            'parameters' => $this->parameters,
                            'responses' => [
                                new Response(['response' => '200', 'description' => 'OK', '_context' => new Context(['version' => '3.0.0'], null)]),
                            ],
                            '_context' => new Context(['version' => '3.0.0'], null),
                        ]),
                        '_context' => new Context(['version' => '3.0.0'], null),
                    ]),
                ];
            }
        };
    }
}
