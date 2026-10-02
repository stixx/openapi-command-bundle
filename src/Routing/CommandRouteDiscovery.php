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

            $files = [];
            foreach ($resource as $file => $info) {
                $files[] = $file;
            }

            $discovered->addCollection($this->directoryLoader->loadFiles($files, CommandRouteDirectoryLoader::TYPE));
            $discovered->addResource($resource);
        }

        return $this->sorter->sort($discovered);
    }

    /**
     * @return array{string, string} the directory before the first glob character, and the pattern after it
     */
    private function split(string $entry): array
    {
        $path = rtrim($entry, '/');
        if ($path === '') {
            throw new LogicException(sprintf('The stixx_openapi_command.command_paths entry "%s" is not a directory. Point it at the directory holding your commands, such as "%%kernel.project_dir%%/src/Command".', $entry));
        }

        $globAt = strcspn($path, '*?{[');
        if ($globAt === strlen($path)) {
            return [$path, ''];
        }

        $slashAt = strrpos(substr($path, 0, $globAt), '/');
        if ($slashAt === false || $slashAt === 0) {
            throw new LogicException(sprintf('The stixx_openapi_command.command_paths entry "%s" has a glob in its first directory. Start it with a fixed directory, such as "%%kernel.project_dir%%/src/*/Command".', $entry));
        }

        return [substr($path, 0, $slashAt), substr($path, $slashAt)];
    }

    private function locate(string $prefix, string $path): string
    {
        $entry = $prefix === $path ? sprintf('"%s"', $path) : sprintf('"%s" (from "%s")', $prefix, $path);

        try {
            $located = $this->locator->locate($prefix);
        } catch (InvalidArgumentException $exception) {
            throw new LogicException(sprintf('The stixx_openapi_command.command_paths directory %s does not exist. Use an absolute path such as "%%kernel.project_dir%%/src/Command".', $entry), previous: $exception);
        }

        if (!is_string($located) || !is_dir($located)) {
            throw new LogicException(sprintf('The stixx_openapi_command.command_paths entry %s is not a directory.', $entry));
        }

        return $located;
    }
}
