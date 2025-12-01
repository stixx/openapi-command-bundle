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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use Stixx\OpenApiCommandBundle\EventSubscriber\RequestValidatorSubscriber;
use Stixx\OpenApiCommandBundle\Tests\Mock\CallCountValidator;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorChain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class RequestValidatorSubscriberTest extends AbstractEventSubscriberTestCase
{
    #[DataProvider('skipRequestProvider')]
    public function testSkipsValidation(int $requestType, string $areaRoute, string $requestRoute): void
    {
        // Arrange
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request();
        $request->attributes->set('_route', $requestRoute);
        $event = new KernelEvent($kernel, $request, $requestType);

        $callCountValidator = new CallCountValidator();
        $requestValidatorChain = new RequestValidatorChain([$callCountValidator]);
        $routes = $this->createNelmioAreaRoutesWithRouteName($areaRoute);
        $subscriber = new RequestValidatorSubscriber($requestValidatorChain, $routes);

        // Act
        $subscriber->validateRequest($event);

        // Assert
        self::assertSame([], $callCountValidator->calls);
    }

    public function testValidatesMainApiRequest(): void
    {
        // Arrange
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request();
        $request->attributes->set('_route', 'api_ok');
        $event = new KernelEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $callCountValidator = new CallCountValidator();
        $requestValidatorChain = new RequestValidatorChain([$callCountValidator]);
        $routes = $this->createNelmioAreaRoutesWithRouteName('api_ok');
        $subscriber = new RequestValidatorSubscriber($requestValidatorChain, $routes);

        // Act
        $subscriber->validateRequest($event);

        // Assert
        self::assertCount(1, $callCountValidator->calls);
        self::assertSame($request, $callCountValidator->calls[0]);
    }
}
