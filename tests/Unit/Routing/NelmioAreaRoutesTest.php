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
        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([]);
        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();

        self::assertFalse($checker->isApiRoute($request));
    }

    public function testReturnsFalseWhenRouteNameIsEmpty(): void
    {
        /** @var ServiceLocator<RouteCollection> $locator */
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

        /** @var ServiceLocator<RouteCollection> $locator */
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

        /** @var ServiceLocator<RouteCollection> $locator */
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

        /** @var ServiceLocator<RouteCollection> $locator */
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

        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([
            'not_a_route_collection' => $notARouteCollection,
            'collection' => static fn () => $collection,
        ]);

        $checker = new NelmioAreaRoutesChecker($locator);

        $request = new Request();
        $request->attributes->set('_route', 'would_match');

        self::assertFalse($checker->isApiRoute($request));
    }

    public function testFallsBackToPathPatternWhenRouteIsMissing(): void
    {
        // Simulates the 404 case: Symfony throws NotFoundHttpException before _route is set.
        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([]);
        $checker = new NelmioAreaRoutesChecker($locator, ['default' => ['^/api']]);

        $request = Request::create('/api/missing');

        self::assertTrue($checker->isApiRoute($request));
    }

    public function testFallsBackToPathPatternWhenRouteIsEmpty(): void
    {
        // Simulates the 405 case: known path, wrong verb — _route is set to '' by the kernel.
        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([]);
        $checker = new NelmioAreaRoutesChecker($locator, ['default' => ['^/api']]);

        $request = Request::create('/api/books/1', 'PATCH');
        $request->attributes->set('_route', '');

        self::assertTrue($checker->isApiRoute($request));
    }

    public function testPathPatternFallbackDoesNotMatchNonApiPath(): void
    {
        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([]);
        $checker = new NelmioAreaRoutesChecker($locator, ['default' => ['^/api']]);

        $request = Request::create('/admin/dashboard');

        self::assertFalse($checker->isApiRoute($request));
    }

    public function testRouteNameMatchTakesPrecedenceOverPathPattern(): void
    {
        // A matched route in a non-API area should still win over a path pattern that would match.
        $collection = new RouteCollection();
        $collection->add('public_route', new Route('/api/public'));

        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([
            'public' => static fn () => $collection,
        ]);
        $checker = new NelmioAreaRoutesChecker($locator, ['admin' => ['^/admin']]);

        $request = Request::create('/api/public');
        $request->attributes->set('_route', 'public_route');

        self::assertTrue($checker->isApiRoute($request));
    }

    public function testMultipleAreasWithDifferentPathPatterns(): void
    {
        /** @var ServiceLocator<RouteCollection> $locator */
        $locator = new ServiceLocator([]);
        $checker = new NelmioAreaRoutesChecker($locator, [
            'public' => ['^/api/public'],
            'internal' => ['^/api/internal'],
        ]);

        self::assertTrue($checker->isApiRoute(Request::create('/api/public/users')));
        self::assertTrue($checker->isApiRoute(Request::create('/api/internal/metrics')));
        self::assertFalse($checker->isApiRoute(Request::create('/api/other')));
    }
}
