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

use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorChain;
use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Request;

final class RequestValidatorChainTest extends TestCase
{
    public function testValidateCallsAllValidators(): void
    {
        // Arrange
        $request = new Request();
        $v1 = $this->createMock(ValidatorInterface::class);
        $v2 = $this->createMock(ValidatorInterface::class);

        $v1->expects(self::once())
            ->method('validate')
            ->with($request);

        $v2->expects(self::once())
            ->method('validate')
            ->with($request);

        $chain = new RequestValidatorChain([$v1, $v2]);

        // Act
        $chain->validate($request);

        // Assert - handled by mock expectations
    }

    public function testValidateWithEmptyChain(): void
    {
        // Arrange
        $request = new Request();
        $chain = new RequestValidatorChain([]);

        // Act
        $chain->validate($request);

        // Assert
        $this->expectNotToPerformAssertions();
    }
}
