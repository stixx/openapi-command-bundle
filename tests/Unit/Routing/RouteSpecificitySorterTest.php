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
use Stixx\OpenApiCommandBundle\Routing\RouteSpecificitySorter;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteSpecificitySorterTest extends TestCase
{
    public function testConcreteRouteIsOrderedBeforeCollidingPlaceholderRoute(): void
    {
        // Arrange — added in the order a filename scan yields (placeholder first), which is the latent bug.
        $routes = new RouteCollection();
        $routes->add('books_get_one', new Route('/api/books/{id}'));
        $routes->add('books_featured', new Route('/api/books/featured'));

        // Act
        $sorted = array_keys((new RouteSpecificitySorter())->sort($routes)->all());

        // Assert
        self::assertSame(['books_featured', 'books_get_one'], $sorted);
    }

    public function testFewerPlaceholdersAreOrderedFirstAcrossDepth(): void
    {
        // Arrange
        $routes = new RouteCollection();
        $routes->add('two', new Route('/api/books/{id}/reviews/{reviewId}'));
        $routes->add('one', new Route('/api/books/{id}/reviews'));
        $routes->add('zero', new Route('/api/books/reviews'));

        // Act
        $sorted = array_keys((new RouteSpecificitySorter())->sort($routes)->all());

        // Assert
        self::assertSame(['zero', 'one', 'two'], $sorted);
    }

    public function testLongerStaticPrefixWinsAmongEqualPlaceholderCounts(): void
    {
        // Arrange — both carry one placeholder; the longer literal prefix is more specific.
        $routes = new RouteCollection();
        $routes->add('short', new Route('/api/{id}'));
        $routes->add('long', new Route('/api/books/{id}'));

        // Act
        $sorted = array_keys((new RouteSpecificitySorter())->sort($routes)->all());

        // Assert
        self::assertSame(['long', 'short'], $sorted);
    }

    public function testEquallySpecificRoutesKeepTheirOriginalOrder(): void
    {
        // Arrange — same placeholder count and static length, so the original order must be preserved.
        $routes = new RouteCollection();
        $routes->add('alpha', new Route('/api/aaa/{id}'));
        $routes->add('beta', new Route('/api/bbb/{id}'));

        // Act
        $sorted = array_keys((new RouteSpecificitySorter())->sort($routes)->all());

        // Assert
        self::assertSame(['alpha', 'beta'], $sorted);
    }
}
