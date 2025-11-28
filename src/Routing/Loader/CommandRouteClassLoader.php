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

use OpenApi\Attributes\Delete as OADelete;
use OpenApi\Attributes\Get as OAGet;
use OpenApi\Attributes\Head as OAHead;
use OpenApi\Attributes\Options as OAOptions;
use OpenApi\Attributes\Patch as OAPatch;
use OpenApi\Attributes\Post as OAPost;
use OpenApi\Attributes\Put as OAPut;
use OpenApi\Annotations\Operation as OAOperation;
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
            $ref->getAttributes(OAOperation::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OAGet::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OAPost::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OAPut::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OAPatch::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OADelete::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OAOptions::class, ReflectionAttribute::IS_INSTANCEOF),
            $ref->getAttributes(OAHead::class, ReflectionAttribute::IS_INSTANCEOF),
        );

        if ($operations === []) {
            return $collection;
        }

        $classController = $this->resolveClassLevelController($ref);

        foreach ($operations as $attr) {
            /** @var OAOperation $op */
            $op = $attr->newInstance();

            $path = $op->path ?? '';
            if ($path === '') {
                continue;
            }

            $methods = $this->methodsFromOperation($op);
            $controller = $this->controllerFromVendorExtension($op) ?? $classController ?? CommandController::class;

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

            $name = $this->routeNameFromOperation($op, $ref);
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
    private function methodsFromOperation(OAOperation $op): array
    {
        $method = property_exists($op, 'method') ? ($op->method ?? '') : '';
        if ($method !== '') {
            return [strtoupper($method)];
        }

        return match (true) {
            $op instanceof OAGet => ['GET'],
            $op instanceof OAPost => ['POST'],
            $op instanceof OAPut => ['PUT'],
            $op instanceof OAPatch => ['PATCH'],
            $op instanceof OADelete => ['DELETE'],
            $op instanceof OAOptions => ['OPTIONS'],
            $op instanceof OAHead => ['HEAD'],
            default => [],
        };
    }

    private function routeNameFromOperation(OAOperation $op, ReflectionClass $class): string
    {
        $operationId = $op->operationId ?? '';
        if ($operationId !== '') {
            return $operationId;
        }

        return $this->defaultNameFromClass($class);
    }

    private function controllerFromVendorExtension(OAOperation $op): ?string
    {
        $x = $op->x ?? null;
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

        /** @var CommandObject $co */
        $co = $attrs[0]->newInstance();
        $controller = $co->controller;

        return (is_string($controller) && $controller !== '') ? $controller : null;
    }
}
