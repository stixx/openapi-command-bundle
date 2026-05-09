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

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

final readonly class NelmioAreaRoutesChecker
{
    /**
     * @param ServiceLocator<RouteCollection> $routesLocator
     * @param array<string, list<string>> $pathPatterns map of area name to its path_patterns regex fragments
     */
    public function __construct(
        private ServiceLocator $routesLocator,
        private array $pathPatterns = [],
    ) {
    }

    public function isApiRoute(Request $request): bool
    {
        $routeName = $request->attributes->get('_route', '');
        if (is_string($routeName) && $routeName !== '' && $this->matchesByRouteName($routeName)) {
            return true;
        }

        // Symfony does not set _route when the path doesn't match any route (404) or when no method
        // matches a known path (405). Fall back to path matching against the area's path_patterns
        // so problem+json is still emitted for paths that the Nelmio area would have covered.
        return $this->matchesByPath($request->getPathInfo());
    }

    private function matchesByRouteName(string $routeName): bool
    {
        foreach (array_keys($this->routesLocator->getProvidedServices()) as $area) {
            $routeCollection = $this->routesLocator->get($area);
            if (!$routeCollection instanceof RouteCollection) {
                return false;
            }

            if (null !== $routeCollection->get($routeName)) {
                return true;
            }
        }

        return false;
    }

    private function matchesByPath(string $path): bool
    {
        foreach ($this->pathPatterns as $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('{'.$pattern.'}', $path) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
