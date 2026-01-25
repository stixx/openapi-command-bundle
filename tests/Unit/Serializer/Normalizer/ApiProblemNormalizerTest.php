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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ApiProblemNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ApiProblemNormalizerTest extends TestCase
{
    public function testSupportsNormalization(): void
    {
        // Arrange
        $normalizer = new ApiProblemNormalizer(false);
        $apiProblem = new ApiProblemException(400);

        // Act & Assert
        self::assertTrue($normalizer->supportsNormalization($apiProblem));
        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
    }

    public function testGetSupportedTypes(): void
    {
        // Arrange
        $normalizer = new ApiProblemNormalizer(false);

        // Act
        $types = $normalizer->getSupportedTypes(null);

        // Assert
        self::assertSame([ApiProblemException::class => true], $types);
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(bool $debug, ApiProblemException $exception, array $expected): void
    {
        // Arrange
        $normalizer = new ApiProblemNormalizer($debug);

        // Act
        $result = $normalizer->normalize($exception);

        // Assert
        self::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{0: bool, 1: ApiProblemException, 2: array<string, mixed>}>
     */
    public static function normalizeProvider(): iterable
    {
        yield 'basic' => [
            false,
            new ApiProblemException(400, 'Title', 'Type'),
            [
                'type' => 'Type',
                'title' => 'Title',
                'status' => 400,
            ],
        ];

        yield 'with instance' => [
            false,
            new ApiProblemException(400, 'Title', 'Type', instance: '/instance'),
            [
                'type' => 'Type',
                'title' => 'Title',
                'status' => 400,
                'instance' => '/instance',
            ],
        ];

        yield 'with detail (debug false)' => [
            false,
            new ApiProblemException(400, 'Title', 'Type', detail: 'Detail'),
            [
                'type' => 'Type',
                'title' => 'Title',
                'status' => 400,
            ],
        ];

        yield 'with detail (debug true)' => [
            true,
            new ApiProblemException(400, 'Title', 'Type', detail: 'Detail'),
            [
                'type' => 'Type',
                'title' => 'Title',
                'status' => 400,
                'detail' => 'Detail',
            ],
        ];
    }

    public function testNormalizeWithViolations(): void
    {
        // Arrange
        $violations = [['field' => 'foo', 'message' => 'bar']];
        $exception = ApiProblemException::badRequest(violations: $violations);
        $normalizer = new ApiProblemNormalizer(false);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects(self::once())
            ->method('normalize')
            ->with($violations, 'json', ['context' => 'foo'])
            ->willReturn($violations);

        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($exception, 'json', ['context' => 'foo']);

        // Assert
        self::assertArrayHasKey('violations', $result);
        self::assertSame($violations, $result['violations']);
    }
}
