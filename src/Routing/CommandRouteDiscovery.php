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

use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Finds command DTOs in the configured paths and turns their OpenAPI operation attributes into routes.
 *
 * @internal
 */
final class CommandRouteDiscovery
{
    private ?RouteCollection $collection = null;

    /**
     * @param list<string> $commandPaths
     */
    public function __construct(
        private readonly CommandRouteDirectoryLoader $directoryLoader,
        private readonly array $commandPaths,
        private readonly RouteSpecificitySorter $sorter = new RouteSpecificitySorter(),
    ) {
    }

    /**
     * Scans every configured path once, sorting the result so concrete paths are matched before templated ones.
     */
    public function discover(): RouteCollection
    {
        return $this->collection ??= $this->scan();
    }

    private function scan(): RouteCollection
    {
        $discovered = new RouteCollection();

        foreach ($this->commandPaths as $path) {
            // Configured paths may legitimately be absent, including the default %kernel.project_dir%/src.
            if (!is_dir($path)) {
                continue;
            }

            $routes = $this->directoryLoader->load($path, CommandRouteDirectoryLoader::TYPE);
            if ($routes instanceof RouteCollection) {
                $discovered->addCollection($routes);
            }
        }

        return $this->sorter->sort($discovered);
    }
}
