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

namespace Stixx\OpenApiCommandBundle\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Reorders command routes so concrete paths are matched before templated ones, following the OpenAPI path-precedence rule.
 *
 * Symfony matches routes in registration order, and a placeholder segment such as `{id}` compiles to `[^/]++`, which also
 * matches a sibling literal segment such as `current`. Ordering more-specific paths first ensures the literal route wins.
 *
 * @internal
 */
final class RouteSpecificitySorter
{
    public function sort(RouteCollection $routes): RouteCollection
    {
        $all = $routes->all();

        $positions = [];
        $position = 0;
        foreach ($all as $name => $route) {
            $positions[$name] = $position;
            ++$position;
        }

        $names = array_keys($all);
        usort($names, function (string $left, string $right) use ($all, $positions): int {
            $byPlaceholders = $this->placeholderCount($all[$left]) <=> $this->placeholderCount($all[$right]);
            if ($byPlaceholders !== 0) {
                return $byPlaceholders;
            }

            $byStaticLength = $this->staticLength($all[$right]) <=> $this->staticLength($all[$left]);
            if ($byStaticLength !== 0) {
                return $byStaticLength;
            }

            return $positions[$left] <=> $positions[$right];
        });

        $sorted = new RouteCollection();
        foreach ($names as $name) {
            $sorted->add($name, $all[$name]);
        }

        return $sorted;
    }

    private function placeholderCount(Route $route): int
    {
        return substr_count($route->getPath(), '{');
    }

    private function staticLength(Route $route): int
    {
        return strlen((string) preg_replace('/\{[^}]*\}/', '', $route->getPath()));
    }
}
