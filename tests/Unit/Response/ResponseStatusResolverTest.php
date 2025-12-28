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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Response;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Response\ResponseStatusResolver;
use Stixx\OpenApiCommandBundle\Tests\Mock\Command\DeleteWithStringResponseCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\Command\ExampleCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\Command\GetWithNon2xxResponsesCommand;
use Stixx\OpenApiCommandBundle\Tests\Mock\Command\PostWithResponsesCommand;
use Symfony\Component\HttpFoundation\Request;

final class ResponseStatusResolverTest extends TestCase
{
    #[DataProvider('defaultMappingProvider')]
    public function testFallsBackToDefaultMappingWhenNoMatchingOperationAttribute(string $method, int $expected): void
    {
        // Arrange
        $resolver = new ResponseStatusResolver();
        $request = new Request();
        $request->setMethod($method);
        $command = new ExampleCommand();

        // Act
        $status = $resolver->resolve($request, $command);

        // Assert
        self::assertSame($expected, $status);
    }

    public static function defaultMappingProvider(): iterable
    {
        yield 'GET => 200' => ['GET', 200];
        yield 'POST => 201' => ['POST', 201];
        yield 'DELETE => 204' => ['DELETE', 204];
        yield 'PUT => 200' => ['PUT', 200];
        yield 'PATCH => 200' => ['PATCH', 200];
        yield 'HEAD => 200' => ['HEAD', 200];
        yield 'OPTIONS => 200' => ['OPTIONS', 200];
        yield 'TRACE => 200' => ['TRACE', 200];
        yield 'Unknown => 200' => ['FOO', 200];
    }

    #[DataProvider('attributeResolutionProvider')]
    public function testResolvesFromOpenApiOperationAttribute(Request $request, object $command, int $expected): void
    {
        // Arrange
        $resolver = new ResponseStatusResolver();

        // Act
        $status = $resolver->resolve($request, $command);

        // Assert
        self::assertSame($expected, $status);
    }

    public static function attributeResolutionProvider(): iterable
    {
        $post = new Request();
        $post->setMethod('POST');
        yield 'POST uses first 2xx (201)' => [$post, new PostWithResponsesCommand(), 201];

        $delete = new Request();
        $delete->setMethod('DELETE');
        yield 'DELETE casts string code 204 to int' => [$delete, new DeleteWithStringResponseCommand(), 204];
    }

    #[DataProvider('nonApplicableAttributesProvider')]
    public function testIgnoresNonMatchingOrNon2xxAttributesAndFallsBack(Request $request, object $command, int $expected): void
    {
        // Arrange
        $resolver = new ResponseStatusResolver();

        // Act
        $status = $resolver->resolve($request, $command);

        // Assert
        self::assertSame($expected, $status);
    }

    public static function nonApplicableAttributesProvider(): iterable
    {
        $get = new Request();
        $get->setMethod('GET');
        yield 'GET ignores POST attribute on command' => [$get, new PostWithResponsesCommand(), 200];

        $get2 = new Request();
        $get2->setMethod('GET');
        yield 'GET with non-2xx responses falls back to 200' => [$get2, new GetWithNon2xxResponsesCommand(), 200];

        $post = new Request();
        $post->setMethod('POST');
        yield 'POST ignores GET attribute and returns 201 default' => [$post, new GetWithNon2xxResponsesCommand(), 201];
    }
}
