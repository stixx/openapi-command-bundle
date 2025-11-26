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

namespace Stixx\OpenApiCommandBundle\RouteDescriber;

use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\RouteDescriber\RouteArgumentDescriber\RouteArgumentDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use Nelmio\ApiDocBundle\Util\SetsContextTrait;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Generator;
use ReflectionClass;
use ReflectionMethod;
use stdClass;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactoryInterface;
use Symfony\Component\Routing\Route;

final class CommandRouteDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;
    use RouteDescriberTrait;
    use SetsContextTrait;

    /**
     * @param iterable<RouteArgumentDescriberInterface> $inlineParameterDescribers
     */
    public function __construct(
        private readonly ArgumentMetadataFactoryInterface $argumentMetadataFactory,
        private readonly iterable $inlineParameterDescribers,
        /**
         * @var array<string, OA\AbstractAnnotation[]>
         */
        private array $attributesCache = [],
    ) {
    }

    public function describe(OA\OpenApi $api, Route $route, ReflectionMethod $reflectionMethod): void
    {
        $commandClass = $this->resolveCommandClass($route);
        if (null === $commandClass) {
            return;
        }

        $supportedHttpMethods = $this->getSupportedHttpMethods($route);
        if ([] === $supportedHttpMethods) {
            return;
        }

        $pathItem = Util::getPath($api, $this->normalizePath($route->getPath()));
        $classReflector = new ReflectionClass($commandClass);
        $context = $this->createContextForCommandClass($classReflector, $pathItem);

        $this->setContext($context);

        $annotations = $this->getAttributesAsAnnotation($classReflector, $context);

        $this->processClassAnnotations($api, $pathItem, $annotations, $supportedHttpMethods);
        $this->runInlineParameterDescribers($pathItem, $reflectionMethod, $supportedHttpMethods);
        $this->setContext(null);
    }

    /**
     * @return string[]
     */
    private function getSupportedHttpMethods(Route $route): array
    {
        $allMethods = Util::OPERATIONS;
        $methods = array_map('strtolower', $route->getMethods());
        if ([] === $methods) {
            return $allMethods;
        }

        return array_values(array_intersect($methods, $allMethods));
    }

    /**
     * @return OA\AbstractAnnotation[]
     */
    private function getAttributesAsAnnotation(ReflectionClass $reflection, Context $context): array
    {
        $class = $reflection->getName();
        if (!isset($this->attributesCache[$class])) {
            $attributesFactory = new AttributeAnnotationFactory();
            $this->attributesCache[$class] = $attributesFactory->build($reflection, $context);
            // restore context (factory clears it)
            $this->setContext($context);
        }

        return $this->attributesCache[$class];
    }

    private function resolveCommandClass(Route $route): ?string
    {
        $commandClass = $route->getDefault('_command_class');
        if (!is_string($commandClass) || $commandClass === '' || !class_exists($commandClass)) {
            return null;
        }

        return $commandClass;
    }

    private function createContextForCommandClass(ReflectionClass $classReflector, OA\PathItem $pathItem): Context
    {
        $context = Util::createContext(['nested' => $pathItem], $pathItem->_context);
        $context->namespace = $classReflector->getNamespaceName();
        $context->class = $classReflector->getShortName();
        $context->filename = $classReflector->getFileName();

        return $context;
    }

    /**
     * @param OA\AbstractAnnotation[] $annotations
     * @param string[] $supportedHttpMethods
     */
    private function processClassAnnotations(OA\OpenApi $api, OA\PathItem $pathItem, array $annotations, array $supportedHttpMethods): void
    {
        $implicitAnnotations = [];
        $mergeProperties = new stdClass();

        foreach ($annotations as $annotation) {
            if ($annotation instanceof OA\Operation) {
                if (!in_array($annotation->method, $supportedHttpMethods, true)) {
                    continue;
                }

                if (Generator::UNDEFINED !== $annotation->path && $pathItem->path !== $annotation->path) {
                    continue;
                }

                $operation = Util::getOperation($pathItem, $annotation->method);
                // Ensure the Command's operationId, when provided, overwrites any existing value.
                // To avoid swagger-php "Multiple definitions" warnings, directly set it on the
                // target operation and clear it from the annotation before merging.
                if (property_exists($annotation, 'operationId') && Generator::UNDEFINED !== $annotation->operationId) {
                    $operation->operationId = $annotation->operationId;
                    $annotation->operationId = Generator::UNDEFINED;
                }

                $operation->mergeProperties($annotation);

                continue;
            }

            if ($annotation instanceof OA\Tag) {
                $annotation->validate();
                $mergeProperties->tags[] = $annotation->name;

                $tag = Util::getTag($api, $annotation->name);
                $tag->mergeProperties($annotation);

                continue;
            }

            if (
                !$annotation instanceof OA\Response
                && !$annotation instanceof OA\RequestBody
                && !$annotation instanceof OA\Parameter
                && !$annotation instanceof OA\ExternalDocumentation
            ) {
                continue;
            }

            $implicitAnnotations[] = $annotation;
        }

        foreach ($supportedHttpMethods as $httpMethod) {
            $operation = Util::getOperation($pathItem, $httpMethod);
            if ([] !== $implicitAnnotations) {
                $operation->merge($implicitAnnotations);
            }
            if ([] !== get_object_vars($mergeProperties)) {
                $operation->mergeProperties($mergeProperties);
            }
        }
    }

    /**
     * Run Nelmio inline parameter describers so route/controller args become parameters.
     *
     * @param string[] $supportedHttpMethods
     */
    private function runInlineParameterDescribers(OA\PathItem $pathItem, ReflectionMethod $reflectionMethod, array $supportedHttpMethods): void
    {
        $controllerCallable = [$reflectionMethod->class, $reflectionMethod->name];
        $argumentMetadataList = $this->argumentMetadataFactory->createArgumentMetadata($controllerCallable, $reflectionMethod);

        foreach ($supportedHttpMethods as $httpMethod) {
            $operation = Util::getOperation($pathItem, $httpMethod);

            foreach ($argumentMetadataList as $argumentMetadata) {
                foreach ($this->inlineParameterDescribers as $inlineParameterDescriber) {
                    if ($inlineParameterDescriber instanceof ModelRegistryAwareInterface) {
                        $inlineParameterDescriber->setModelRegistry($this->modelRegistry);
                    }

                    $inlineParameterDescriber->describe($argumentMetadata, $operation);
                }
            }
        }
    }
}
