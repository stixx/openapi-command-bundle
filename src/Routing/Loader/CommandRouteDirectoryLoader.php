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

    public function load(mixed $path, ?string $type = null): ?RouteCollection
    {
        if (!is_string($path) || !is_dir($dir = $this->locator->locate($path))) {
            return parent::load($path, $type);
        }

        $collection = new RouteCollection();
        $collection->addResource(new GlobResource($dir, '/*.php', true));

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
            if (++$scanned % self::GC_INTERVAL === 0) {
                gc_collect_cycles();
            }

            /** @var class-string|false $class */
            $class = $this->findClass($file);
            if ($class === false) {
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
