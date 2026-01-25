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

use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractEventSubscriberTestCase extends TestCase
{
    /**
     * @return iterable<string, array{0: int, 1: string, 2: string}>
     */
    public static function skipRequestProvider(): iterable
    {
        yield 'sub-request' => [
            HttpKernelInterface::SUB_REQUEST,
            'api_route',
            'api_route',
        ];

        yield 'main request non-api route' => [
            HttpKernelInterface::MAIN_REQUEST,
            'some_api',
            'not_api',
        ];
    }

    protected function createNelmioAreaRoutesWithRouteName(string $routeName): NelmioAreaRoutesChecker
    {
        $collection = new RouteCollection();
        $collection->add($routeName, new Route('/'.$routeName));

        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([
            'default' => static fn () => $collection,
        ]);

        return new NelmioAreaRoutesChecker($locator);
    }
}
