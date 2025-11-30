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

use OpenApi\Attributes as OA;
use OpenApi\Annotations\Operation;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Stixx\OpenApiCommandBundle\Controller\CommandController;
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

        $operations = array_merge(
            $ref->getAttributes(OA\Get::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OA\Post::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OA\Put::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OA\Patch::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OA\Delete::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OA\Options::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OA\Head::class, ReflectionAttribute::IS_INSTANCEOF),
        );

        if ($operations === []) {
            return $collection;
        }

        $classController = $this->resolveClassLevelController($ref);

        foreach ($operations as $attribute) {
            $operation = $attribute->newInstance();
            if (!$operation instanceof Operation) {
                continue;
            }

            $path = $operation->path ?? '';
            if ($path === '') {
                continue;
            }

            $methods = $this->methodsFromOperation($operation);
            $controller = $this->controllerFromVendorExtension($operation) ?? $classController ?? CommandController::class;

            $route = $this->createRoute(
                path: $path,
                defaults: [
                    '_controller' => $controller,
                    '_command_class' => $class,
                ],
                requirements: [],
                options: [],
                host: null,
                schemes: [],
                methods: $methods,
                condition: null,
            );

            $name = $this->routeNameFromOperation($operation, $ref);
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

    /**
     * @return list<string>
     */
    private function methodsFromOperation(Operation $operation): array
    {
        $method = property_exists($operation, 'method') ? ($operation->method ?? '') : '';
        if ($method !== '') {
            return [strtoupper($method)];
        }

        return match (true) {
            $operation instanceof OA\Get => ['GET'],
            $operation instanceof OA\Post => ['POST'],
            $operation instanceof OA\Put => ['PUT'],
            $operation instanceof OA\Patch => ['PATCH'],
            $operation instanceof OA\Delete => ['DELETE'],
            $operation instanceof OA\Options => ['OPTIONS'],
            $operation instanceof OA\Head => ['HEAD'],
            default => [],
        };
    }

    private function routeNameFromOperation(Operation $operation, ReflectionClass $class): string
    {
        $operationId = $operation->operationId ?? '';
        if ($operationId !== '') {
            return $operationId;
        }

        return $this->defaultNameFromClass($class);
    }

    private function controllerFromVendorExtension(Operation $operation): ?string
    {
        $x = $operation->x ?? null;
        if (is_array($x)) {
            $controller = $x['controller'] ?? null;
            if (is_string($controller) && $controller !== '') {
                return $controller;
            }
        }

        return null;
    }

    private function resolveClassLevelController(ReflectionClass $class): ?string
    {
        $attrs = $class->getAttributes(CommandObject::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attrs === []) {
            return null;
        }

        $commandObject = $attrs[0]?->newInstance();
        if (!$commandObject instanceof CommandObject) {
            return null;
        }

        $controller = $commandObject->controller;

        return (is_string($controller) && $controller !== '') ? $controller : null;
    }
}
