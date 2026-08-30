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

namespace Stixx\OpenApiCommandBundle\Routing\Loader;

use Stixx\OpenApiCommandBundle\Routing\CommandRouteDiscovery;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the discovered command routes to the application's route collection.
 *
 * Decorates `routing.loader`, which the router calls exactly once for the root routing resource, so discovery
 * runs whichever loader the application's own routes happen to use. That service is FrameworkBundle's
 * DelegatingLoader, which is not itself tagged `routing.loader`, so the decoration cannot recurse through
 * nested imports.
 *
 * @internal
 */
final class RouterLoaderDecorator implements LoaderInterface
{
    // $inner is the abstract Loader, not LoaderInterface: MicroKernelTrait passes this service to
    // configureRoutes(), where RoutingConfigurator::import() calls import() — declared only on Loader.
    public function __construct(
        private readonly Loader $inner,
        private readonly CommandRouteDiscovery $discovery,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        $collection = $this->inner->load($resource, $type);
        if (!$collection instanceof RouteCollection) {
            return $collection;
        }

        foreach ($this->discovery->discover()->all() as $name => $route) {
            // Keep a route the application already declared: re-adding moves it to the end of the
            // collection, changing which route matches first.
            if ($collection->get($name) !== null) {
                continue;
            }

            $collection->add($name, $route);
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }

    public function import(mixed $resource, ?string $type = null): mixed
    {
        return $this->inner->import($resource, $type);
    }
}
