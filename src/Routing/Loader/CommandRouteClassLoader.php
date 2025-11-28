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

use OpenApi\Attributes\Operation;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Stixx\OpenApiCommandBundle\Controller\CommandController;
use Symfony\Component\Routing\Attribute\Route as SymfonyRouteAttribute;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class CommandRouteClassLoader extends AttributeClassLoader
{
    /**
     * @param list<string> $controllerClasses
     */
    public function __construct(
        ?string $env = null,
        private readonly array $controllerClasses = []
    ) {
        parent::__construct($env);
    }

    /**
     * @param class-string $class
     */
    public function load(mixed $class, ?string $type = null): RouteCollection
    {
        $collection = new RouteCollection();

        if (!is_string($class) || $class === '' || !class_exists($class)) {
            return $collection;
        }

        $ref = new ReflectionClass($class);
        if ($ref->isAbstract()) {
            return $collection;
        }

        if (isset($this->controllerClasses[$class])) {
            return $collection;
        }

        $hasOpenApiOperation = $ref->getAttributes(Operation::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
        if (!$hasOpenApiOperation) {
            return $collection;
        }

        // Only collect class-level Route attributes (commands are not controllers).
        $attrs = $ref->getAttributes(SymfonyRouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
        foreach ($attrs as $attr) {
            /** @var SymfonyRouteAttribute $routeAttr */
            $routeAttr = $attr->newInstance();

            $path = (string) $routeAttr->getPath();
            if ($path === '') {
                continue;
            }

            $defaults = $routeAttr->getDefaults();
            $defaults['_controller'] = CommandController::class;
            $defaults['_command_class'] = $class;

            $route = $this->createRoute(
                path: $path,
                defaults: $defaults,
                requirements: $routeAttr->getRequirements(),
                options: $routeAttr->getOptions(),
                host: $routeAttr->getHost(),
                schemes: $routeAttr->getSchemes(),
                methods: $routeAttr->getMethods(),
                condition: $routeAttr->getCondition(),
            );

            $name = $routeAttr->getName() ?? $this->defaultNameFromClass($ref);
            $finalName = $this->ensureUniqueName($collection, $name);
            $collection->add($finalName, $route);
        }

        return $collection;
    }

    protected function configureRoute(Route $route, ReflectionClass $class, ReflectionMethod $method, object $attr): void
    {
    }

    private function defaultNameFromClass(ReflectionClass $class): string
    {
        $short = $class->getShortName();
        $base = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $short));
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
