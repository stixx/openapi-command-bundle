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
use Stixx\OpenApiCommandBundle\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ConstraintViolationListNormalizerTest extends TestCase
{
    public function testSupportsNormalization(): void
    {
        // Arrange
        $normalizer = new ConstraintViolationListNormalizer();
        $list = $this->createMock(ConstraintViolationListInterface::class);

        // Act & Assert
        self::assertTrue($normalizer->supportsNormalization($list));
        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
    }

    public function testGetSupportedTypes(): void
    {
        // Arrange
        $normalizer = new ConstraintViolationListNormalizer();

        // Act
        $types = $normalizer->getSupportedTypes(null);

        // Assert
        self::assertSame([ConstraintViolationListInterface::class => true], $types);
    }

    public function testNormalize(): void
    {
        // Arrange
        $violation1 = $this->createMock(ConstraintViolationInterface::class);
        $violation2 = $this->createMock(ConstraintViolationInterface::class);
        $list = new ConstraintViolationList([$violation1, $violation2]);

        $normalizer = new ConstraintViolationListNormalizer();
        $innerNormalizer = $this->createMock(NormalizerInterface::class);

        $innerNormalizer->expects(self::exactly(2))
            ->method('normalize')
            ->willReturnMap([
                [$violation1, 'json', [], ['norm1']],
                [$violation2, 'json', [], ['norm2']],
            ]);

        $normalizer->setNormalizer($innerNormalizer);

        // Act
        $result = $normalizer->normalize($list, 'json');

        // Assert
        self::assertEquals([['norm1'], ['norm2']], $result);
    }
}
