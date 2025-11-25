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

final readonly class NelmioAreaRoutes
{
    public function __construct(private ServiceLocator $routesLocator)
    {
    }

    public function isApiRoute(Request $request): bool
    {
        $routeName = (string) $request->attributes->get('_route', '');
        if ('' === $routeName) {
            return false;
        }

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
}
