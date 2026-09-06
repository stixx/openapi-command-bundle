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
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Annotations\Info;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Post;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OpenApi\Context;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Stixx\OpenApiCommandBundle\Validator\RequestValidator;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RequestValidatorTest extends TestCase
{
    public function testValidateSuccessful(): void
    {
        // Arrange
        $describer = $this->createDescriber([]);

        $validator = new RequestValidator(new ApiDocGenerator([$describer], []), $this->createPsrHttpFactory());

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
                'schema' => new Schema(['schema' => 'header-schema', 'type' => 'string', '_context' => new Context(['version' => '3.0.0'], null)]),
                '_context' => new Context(['version' => '3.0.0'], null),
            ]),
        ]);

        $validator = new RequestValidator(new ApiDocGenerator([$describer], []), $this->createPsrHttpFactory());

        $request = Request::create('/test', 'POST');
        // The request is missing the 'X-Required-Header' defined in the OpenAPI spec above

        // Act & Assert
        $this->expectException(ValidationFailed::class);
        $validator->validate($request);
    }

    public function testValidateOnlyGeneratesOpenApiDocumentOnce(): void
    {
        // Arrange
        $describer = new class () implements DescriberInterface {
            public int $describeCalls = 0;

            public function describe(OpenApi $api): void
            {
                ++$this->describeCalls;

                $api->openapi = '3.0.0';
                $api->info = new Info(['title' => 'Test', 'version' => '1.0.0', '_context' => new Context(['version' => '3.0.0'], null)]);
                $api->paths = [
                    new PathItem([
                        'path' => '/test',
                        'post' => new Post([
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

        $validator = new RequestValidator(new ApiDocGenerator([$describer], []), $this->createPsrHttpFactory());

        // Act
        $validator->validate(Request::create('/test', 'POST'));
        $validator->validate(Request::create('/test', 'POST'));
        $validator->validate(Request::create('/test', 'POST'));

        // Assert: the OpenAPI document is built once and reused for subsequent requests.
        self::assertSame(1, $describer->describeCalls);
    }

    public function testValidatesAgainstTheRequestsOwnAreaNotTheDefaultArea(): void
    {
        // Arrange
        $defaultGenerator = new ApiDocGenerator([$this->createDescriberForPath('/default')], []);
        $internalGenerator = new ApiDocGenerator([$this->createDescriberForPath('/internal')], []);

        $request = Request::create('/internal', 'POST');
        $request->attributes->set('_route', 'internal_route');

        $validator = new RequestValidator(
            $defaultGenerator,
            $this->createPsrHttpFactory(),
            $this->createGeneratorsLocator($defaultGenerator, $internalGenerator),
            $this->createAreaChecker(['default' => '/default', 'internal' => '/internal']),
        );

        // Act
        $validator->validate($request);

        // Assert: /internal exists only in the internal area's document; the default document would raise NoPath.
        $this->expectNotToPerformAssertions();
    }

    public function testKeepsASeparateValidatorPerArea(): void
    {
        // Arrange
        $defaultGenerator = new ApiDocGenerator([$this->createDescriberForPath('/default')], []);
        $internalGenerator = new ApiDocGenerator([$this->createDescriberForPath('/internal')], []);

        $validator = new RequestValidator(
            $defaultGenerator,
            $this->createPsrHttpFactory(),
            $this->createGeneratorsLocator($defaultGenerator, $internalGenerator),
            $this->createAreaChecker(['default' => '/default', 'internal' => '/internal']),
        );

        $internal = Request::create('/internal', 'POST');
        $internal->attributes->set('_route', 'internal_route');

        $default = Request::create('/default', 'POST');
        $default->attributes->set('_route', 'default_route');

        // Act: the internal area first, so its validator is memoised before the default area is used.
        $validator->validate($internal);
        $validator->validate($default);

        // Assert: a shared memo would validate /default against the internal document and raise NoPath.
        $this->expectNotToPerformAssertions();
    }

    private function createPsrHttpFactory(): PsrHttpFactory
    {
        $psr17 = new Psr17Factory();

        return new PsrHttpFactory($psr17, $psr17, $psr17, $psr17);
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

    private function createDescriberForPath(string $path): DescriberInterface
    {
        return new class ($path) implements DescriberInterface {
            public function __construct(private readonly string $path)
            {
            }

            public function describe(OpenApi $api): void
            {
                $api->info = new Info(['title' => 'Test', 'version' => '1.0.0', '_context' => new Context(['version' => '3.0.0'], null)]);
                $api->paths = [
                    new PathItem([
                        'path' => $this->path,
                        'post' => new Post([
                            'responses' => [new Response(['response' => 200, 'description' => 'ok', '_context' => new Context(['version' => '3.0.0'], null)])],
                            '_context' => new Context(['version' => '3.0.0'], null),
                        ]),
                        '_context' => new Context(['version' => '3.0.0'], null),
                    ]),
                ];
            }
        };
    }

    /** @return ServiceLocator<ApiDocGenerator> */
    private function createGeneratorsLocator(ApiDocGenerator $default, ApiDocGenerator $internal): ServiceLocator
    {
        /** @var ServiceLocator<ApiDocGenerator> $locator */
        $locator = new ServiceLocator([
            'default' => static fn (): ApiDocGenerator => $default,
            'internal' => static fn (): ApiDocGenerator => $internal,
        ]);

        return $locator;
    }

    /** @param array<string, string> $areaToPath */
    private function createAreaChecker(array $areaToPath): NelmioAreaRoutesChecker
    {
        $routes = [];
        $patterns = [];
        foreach ($areaToPath as $area => $path) {
            $collection = new RouteCollection();
            $collection->add($area.'_route', new Route($path));
            $routes[$area] = static fn (): RouteCollection => $collection;
            $patterns[$area] = ['^'.preg_quote($path, '{}')];
        }

        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator($routes);

        return new NelmioAreaRoutesChecker($locator, $patterns);
    }
}
