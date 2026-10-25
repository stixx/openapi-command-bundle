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

use PHPUnit\Framework\TestCase;
use stdClass;
use Stixx\OpenApiCommandBundle\Exception\ApiProblemException;
use Stixx\OpenApiCommandBundle\Model\ProblemDetails;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ApiProblemNormalizer;

final class ApiProblemNormalizerTest extends TestCase
{
    public function testSupportsOnlyApiProblems(): void
    {
        // Arrange
        $normalizer = new ApiProblemNormalizer();

        // Act & Assert
        self::assertTrue($normalizer->supportsNormalization(ApiProblemException::serverError()));
        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
        self::assertSame([ApiProblemException::class => true], $normalizer->getSupportedTypes(null));
    }

    public function testLeavesOutTheMembersThatAreNotSet(): void
    {
        // Act
        $payload = new ApiProblemNormalizer()->normalize(ApiProblemException::serverError());

        // Assert
        self::assertSame(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500], $payload);
    }

    public function testWritesTheMembersOfASubclassAfterTheStandardOnes(): void
    {
        // Arrange
        $problem = new class (409, 'Stock is reserved', 'https://example.com/problems/out-of-stock', '/orders/7', 'SKU-1') extends ProblemDetails {
            public function __construct(int $status, ?string $detail, string $type, ?string $instance, public readonly string $sku)
            {
                parent::__construct($status, 'Out of stock', $detail, $type, $instance);
            }
        };

        // Act
        $payload = new ApiProblemNormalizer()->normalize(new ApiProblemException($problem));

        // Assert
        self::assertSame([
            'type' => 'https://example.com/problems/out-of-stock',
            'title' => 'Out of stock',
            'status' => 409,
            'detail' => 'Stock is reserved',
            'instance' => '/orders/7',
            'sku' => 'SKU-1',
        ], $payload);
    }

    public function testWritesEveryFieldOfAViolation(): void
    {
        // Arrange
        $exception = ApiProblemException::badRequest(violations: [new Violation('title', 'Value cannot be null', 'nullable', 'openapi_request_validation')]);

        // Act
        $json = json_encode(new ApiProblemNormalizer()->normalize($exception), JSON_THROW_ON_ERROR);

        // Assert
        self::assertSame(
            '{"type":"about:blank","title":"Bad Request","status":400,"violations":[{"propertyPath":"title","message":"Value cannot be null","code":"nullable","constraint":"openapi_request_validation","error":null}]}',
            $json,
        );
    }
}
