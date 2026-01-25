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
use ReflectionClass;
use stdClass;
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ConstraintViolationNormalizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationInterface;

final class ConstraintViolationNormalizerTest extends TestCase
{
    public function testSupportsNormalization(): void
    {
        // Arrange
        $normalizer = new ConstraintViolationNormalizer();
        $violation = $this->createMock(ConstraintViolationInterface::class);

        // Act & Assert
        self::assertTrue($normalizer->supportsNormalization($violation));
        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
    }

    public function testGetSupportedTypes(): void
    {
        // Arrange
        $normalizer = new ConstraintViolationNormalizer();

        // Act
        $types = $normalizer->getSupportedTypes(null);

        // Assert
        self::assertSame([ConstraintViolationInterface::class => true], $types);
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('normalizeProvider')]
    public function testNormalize(ConstraintViolationInterface $violation, array $expected): void
    {
        // Arrange
        $normalizer = new ConstraintViolationNormalizer();

        // Act
        $result = $normalizer->normalize($violation);

        // Assert
        self::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{0: ConstraintViolationInterface, 1: array<string, mixed>}>
     */
    public static function normalizeProvider(): iterable
    {
        $violation = self::createMockViolation('prop', 'msg', 'CODE123', null);
        yield 'basic violation' => [
            $violation,
            [
                'propertyPath' => 'prop',
                'message' => 'msg',
                'code' => 'CODE123',
                'constraint' => null,
                'error' => null,
            ],
        ];

        $constraint = new class () extends Constraint {
            public const string SOME_ERROR = 'SOME_ERROR_CODE';
        };
        $violation = self::createMockViolation('prop', 'msg', 'SOME_ERROR_CODE', $constraint);
        yield 'violation with constraint and mapped error' => [
            $violation,
            [
                'propertyPath' => 'prop',
                'message' => 'msg',
                'code' => 'SOME_ERROR_CODE',
                'constraint' => (new ReflectionClass($constraint))->getShortName(),
                'error' => 'SOME_ERROR',
            ],
        ];

        $violation = self::createMockViolation('', 'msg', null, null);
        yield 'minimal violation' => [
            $violation,
            [
                'propertyPath' => null,
                'message' => 'msg',
                'code' => null,
                'constraint' => null,
                'error' => null,
            ],
        ];
    }

    private static function createMockViolation(string $path, string $message, ?string $code, ?Constraint $constraint): ConstraintViolationInterface
    {
        $mock = (new class ('stub') extends TestCase {})->createMock(ConstraintViolationInterface::class);
        $mock->method('getPropertyPath')->willReturn($path);
        $mock->method('getMessage')->willReturn($message);
        $mock->method('getCode')->willReturn($code);
        $mock->method('getConstraint')->willReturn($constraint);

        return $mock;
    }
}
