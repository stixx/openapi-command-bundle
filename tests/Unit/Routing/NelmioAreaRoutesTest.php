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

namespace Stixx\OpenApiCommandBundle\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRoutesChecker;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class NelmioAreaRoutesTest extends TestCase
{
    public function testReturnsFalseWhenNoRouteAttributePresent(): void
    {
        $locator = new ServiceLocator([]);
        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();

        self::assertFalse($checker->isApiRoute($request));
    }

    public function testReturnsFalseWhenRouteNameIsEmpty(): void
    {
        $locator = new ServiceLocator([]);
        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();
        $request->attributes->set('_route', '');

        self::assertFalse($checker->isApiRoute($request));
    }

    public function testReturnsTrueWhenRouteExistsInAnyArea(): void
    {
        $collection = new RouteCollection();
        $collection->add('api_route', new Route('/api'));

        $locator = new ServiceLocator([
            'default' => static fn () => $collection,
        ]);

        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();
        $request->attributes->set('_route', 'api_route');

        self::assertTrue($checker->isApiRoute($request));
    }

    public function testReturnsFalseWhenRouteDoesNotExistInAnyArea(): void
    {
        $collection = new RouteCollection();
        $collection->add('another_route', new Route('/other'));

        $locator = new ServiceLocator([
            'default' => static fn () => $collection,
        ]);

        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();
        $request->attributes->set('_route', 'missing_route');

        self::assertFalse($checker->isApiRoute($request));
    }

    public function testReturnsTrueWhenFoundInSecondArea(): void
    {
        $first = new RouteCollection();
        $first->add('first_only', new Route('/first'));

        $second = new RouteCollection();
        $second->add('target', new Route('/target'));

        $locator = new ServiceLocator([
            'area_one' => static fn () => $first,
            'area_two' => static fn () => $second,
        ]);

        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();
        $request->attributes->set('_route', 'target');

        self::assertTrue($checker->isApiRoute($request));
    }

    public function testNonRouteCollectionServiceCausesFalse(): void
    {
        $notARouteCollection = static fn () => (object) ['not' => 'a route collection'];

        $collection = new RouteCollection();
        $collection->add('would_match', new Route('/match'));

        $locator = new ServiceLocator([
            'not_a_route_collection' => $notARouteCollection,
            'collection' => static fn () => $collection,
        ]);

        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();
        $request->attributes->set('_route', 'would_match');

        self::assertFalse($checker->isApiRoute($request));
    }
}
