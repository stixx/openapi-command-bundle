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

use InvalidArgumentException;
use LogicException;
use Stixx\OpenApiCommandBundle\Routing\Loader\CommandRouteDirectoryLoader;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Routing\RouteCollection;

/**
 * Finds command DTOs under the configured paths and turns their OpenAPI operation attributes into routes.
 *
 * @internal
 */
final class CommandRouteDiscovery
{
    private ?RouteCollection $collection = null;

    /**
     * @param list<string> $commandPaths directories or glob patterns
     */
    public function __construct(
        private readonly CommandRouteDirectoryLoader $directoryLoader,
        private readonly FileLocatorInterface $locator,
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
            [$prefix, $pattern] = $this->split($path);
            $resource = new GlobResource($this->locate($prefix, $path), $pattern.'/**/*.php', false);

            /** @var list<string> $files */
            $files = array_keys(iterator_to_array($resource));

            $discovered->addCollection($this->directoryLoader->loadFiles($files, CommandRouteDirectoryLoader::TYPE));
            $discovered->addResource($resource);
        }

        return $this->sorter->sort($discovered);
    }

    /**
     * @return array{string, string} the directory before the first glob character, and the pattern after it
     */
    private function split(string $path): array
    {
        $path = rtrim($path, '/');
        $globAt = strcspn($path, '*?{[');
        if ($globAt === strlen($path)) {
            return [$path, ''];
        }

        $slashAt = strrpos(substr($path, 0, $globAt), '/');
        if ($slashAt === false) {
            return ['', '/'.$path];
        }

        return [substr($path, 0, $slashAt), substr($path, $slashAt)];
    }

    private function locate(string $prefix, string $path): string
    {
        try {
            $located = $this->locator->locate($prefix);
        } catch (InvalidArgumentException) {
            $located = null;
        }

        if (!is_string($located) || !is_dir($located)) {
            throw new LogicException(sprintf('The directory "%s" of the stixx_openapi_command.command_paths entry "%s" does not exist.', $prefix, $path));
        }

        return $located;
    }
}
