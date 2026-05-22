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

namespace Stixx\OpenApiCommandBundle\Controller\ArgumentResolver;

use JsonException;
use ReflectionClass;
use ReflectionNamedType;
use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final readonly class CommandValueResolver implements ValueResolverInterface
{
    public function __construct(
        private DenormalizerInterface $serializer,
    ) {
    }

    /**
     * @return iterable<object>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $this->resolveTargetClass($request, $argument);
        if ($type === null) {
            return [];
        }

        $hasBody = $this->hasRequestBody($request);
        if ($hasBody) {
            $this->assertJsonContentType($request);
        }

        $params = $this->extractScalarsFromRouteAndQuery($request);
        $payload = $hasBody ? $this->decodeJsonBodyToArray($request) : [];
        if ($params !== []) {
            $payload = array_replace($payload, $params);
        }

        $payload = $this->coerceScalarsAgainstConstructor($payload, $type);

        yield $this->denormalizeToType($payload, $type);
    }

    private function resolveTargetClass(Request $request, ArgumentMetadata $argument): ?string
    {
        $attrs = $argument->getAttributes(CommandObject::class, ArgumentMetadata::IS_INSTANCEOF);
        $attr = $attrs[0] ?? null;
        if (!$attr instanceof CommandObject) {
            return null;
        }

        $type = $attr->class ?: $argument->getType();
        if ($type && $type !== 'object' && $type !== 'mixed') {
            return $type;
        }

        $routeClass = $request->attributes->get('_command_class');
        if (is_string($routeClass) && $routeClass !== '') {
            return $routeClass;
        }

        return null;
    }

    private function hasRequestBody(Request $request): bool
    {
        $content = $request->getContent();

        return $content !== '' && trim((string) $content) !== '';
    }

    private function assertJsonContentType(Request $request): void
    {
        $contentType = $request->headers->get('Content-Type');

        if ($contentType === null) {
            throw new BadRequestHttpException('Unsupported Content-Type. Expecting application/json');
        }

        $parts = HeaderUtils::split($contentType, ';,');
        /** @var array<int, array<int, string>> $parts */
        $mediaType = strtolower(trim($parts[0][0] ?? ''));
        $isJson = ($mediaType === 'application/json') || str_ends_with($mediaType, '+json');

        if (!$isJson) {
            throw new BadRequestHttpException('Unsupported Content-Type. Expecting application/json');
        }
    }

    /**
     * Collects scalar route attributes (excluding keys starting with "_") merged with scalar query
     * parameters. Path attributes are authoritative — `/books/{id}` cannot be redirected by `?id=...`.
     *
     * @return array<string, scalar|null>
     */
    private function extractScalarsFromRouteAndQuery(Request $request): array
    {
        $attributes = $request->attributes->all();
        $routeData = array_filter(
            $attributes,
            static function ($value, $key): bool {
                return $key !== ''
                    && $key[0] !== '_'
                    && (is_scalar($value) || $value === null);
            },
            ARRAY_FILTER_USE_BOTH
        );

        $queryData = $request->query->all();
        $filteredQuery = array_filter(
            $queryData,
            static function ($value): bool {
                return is_scalar($value);
            }
        );

        return array_replace($filteredQuery, $routeData);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBodyToArray(Request $request): array
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON body: '.$exception->getMessage(), $exception);
        }

        return $decoded;
    }

    /**
     * Coerces query/route string scalars to the declared `int`/`float`/`bool` constructor types.
     * Invalid input stays as a string so the denormalizer surfaces its normal error.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function coerceScalarsAgainstConstructor(array $payload, string $type): array
    {
        if (!class_exists($type)) {
            return $payload;
        }

        $reflection = new ReflectionClass($type);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $payload;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!array_key_exists($name, $payload)) {
                continue;
            }

            $value = $payload[$name];
            if (!is_string($value)) {
                continue;
            }

            $parameterType = $parameter->getType();
            if (!$parameterType instanceof ReflectionNamedType) {
                continue;
            }

            $coerced = match ($parameterType->getName()) {
                'int'   => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
                'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                default => null,
            };

            if ($coerced !== null) {
                $payload[$name] = $coerced;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function denormalizeToType(array $data, string $type): object
    {
        try {
            $object = $this->serializer->denormalize($data, $type);

            if (!is_object($object)) {
                throw new NotNormalizableValueException(sprintf('Expected object, got %s', get_debug_type($object)));
            }

            return $object;
        } catch (NotEncodableValueException|NotNormalizableValueException $exception) {
            throw new BadRequestHttpException('Unable to map request to command: '.$exception->getMessage(), $exception);
        }
    }
}
