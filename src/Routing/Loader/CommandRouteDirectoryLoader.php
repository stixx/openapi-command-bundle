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

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
final class CommandRouteDirectoryLoader extends AttributeDirectoryLoader
{
    public const string TYPE = 'stixx_openapi_command.command_attributes';

    private const int GC_INTERVAL = 50;

    public function __construct(FileLocatorInterface $locator, CommandRouteClassLoader $loader)
    {
        parent::__construct($locator, $loader);
    }

    /**
     * Mirrors the parent with a smaller memory footprint: discovery scans the whole of `src` by default, so the
     * file list and the attribute garbage scale with the application rather than with its commands.
     */
    public function load(mixed $path, ?string $type = null): ?RouteCollection
    {
        if (!is_string($path) || !is_dir($dir = $this->locator->locate($path))) {
            return parent::load($path, $type);
        }

        $collection = new RouteCollection();
        $collection->addResource(new GlobResource($dir, '/*.php', true));

        // Path strings rather than SplFileInfo objects: the list spans every file in the tree, and at ~1.5KB per
        // object it dominated peak memory for a large `src`.
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::CURRENT_AS_PATHNAME),
                static fn (string $current, string $key, RecursiveDirectoryIterator $iterator): bool => !str_starts_with(basename($current), '.')
                    && ($iterator->hasChildren() || str_ends_with($current, '.php')),
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iterator as $file) {
            if (is_string($file) && is_file($file)) {
                $files[] = $file;
            }
        }
        sort($files, SORT_STRING);

        $scanned = 0;
        foreach ($files as $file) {
            // swagger-php annotations reference their Context and back, so every instantiated operation attribute
            // leaves cyclic garbage that only the cycle collector frees. Collecting now and then caps the peak.
            if (++$scanned % self::GC_INTERVAL === 0) {
                gc_collect_cycles();
            }

            $class = $this->findClass($file);
            if ($class === false || !class_exists($class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $collection->addCollection($this->loader->load($class, $type));
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return is_string($resource) && $type === self::TYPE;
    }
}
