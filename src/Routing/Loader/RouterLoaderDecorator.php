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
 * @see CommandRouteDiscovery
 *
 * This decorates `routing.loader` (FrameworkBundle's `DelegatingLoader`) rather than one of the individual
 * loaders behind it. The router resolves `routing.loader` to build its collection and calls it exactly once,
 * for the root routing resource, so command routes are registered no matter how the application declares its
 * own routes — or whether it declares any at all.
 *
 * Decorating a specific loader instead makes discovery conditional on the application happening to use it. The
 * previous implementation decorated `routing.loader.attribute.directory`, which the current Symfony skeleton
 * never invokes: its `config/routes.yaml` sets a `namespace`, so routes load through `Psr4DirectoryLoader`, and
 * command routes silently disappeared.
 *
 * `DelegatingLoader` is not itself tagged `routing.loader`, so it is absent from the resolver that nested
 * imports go through. Decorating it therefore cannot recurse.
 *
 * @internal
 */
final class RouterLoaderDecorator implements LoaderInterface
{
    /**
     * `$inner` is typed as the abstract Loader rather than LoaderInterface because MicroKernelTrait hands the
     * decorated service to `configureRoutes()`, and RoutingConfigurator::import() calls import() on it — a
     * method Loader declares but LoaderInterface does not.
     */
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
            // An application may already have imported a command explicitly. Keep its route: re-adding would
            // move the route to the end of the collection and change which one matches first.
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
