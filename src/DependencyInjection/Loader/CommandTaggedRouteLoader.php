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

use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class CommandTaggedRouteLoader extends Loader
{
    public const string TYPE = 'stixx_openapi_command.command_attributes';

    private bool $loaded = false;

    /**
     * @param array<int, array<string, mixed>> $taggedRoutes
     */
    public function __construct(
        private readonly array $taggedRoutes = [],
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            return new RouteCollection();
        }

        $collection = new RouteCollection();

        foreach ($this->taggedRoutes as $meta) {
            $class = (string) ($meta['class'] ?? '');
            $path = (string) ($meta['path'] ?? '');

            if ($class === '' || $path === '') {
                continue;
            }

            $defaults = array_merge((array) ($meta['defaults'] ?? []), [
                '_controller' => CommandController::class,
                '_command_class' => $class,
            ]);

            $route = new Route(
                path: $path,
                defaults: $defaults,
                requirements: (array) ($meta['requirements'] ?? []),
                options: (array) ($meta['options'] ?? []),
                host: $meta['host'] ?? null,
                schemes: (array) ($meta['schemes'] ?? []),
                methods: (array) ($meta['methods'] ?? []),
                condition: $meta['condition'] ?? null,
            );

            $name = (string) ($meta['name'] ?? '') ?: $this->generateRouteName($class);
            $final = $this->ensureUniqueName($collection, $name);
            $collection->add($final, $route);
        }

        $this->loaded = true;

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }

    private function generateRouteName(string $class): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', ltrim(strrchr($class, '\\') ?: $class, '\\')));

        return 'command_'.$base;
    }

    private function ensureUniqueName(RouteCollection $collection, string $name): string
    {
        if (null === $collection->get($name)) {
            return $name;
        }

        $i = 2;
        while (null !== $collection->get($name.'_'.$i)) {
            ++$i;
        }

        return $name.'_'.$i;
    }
}
