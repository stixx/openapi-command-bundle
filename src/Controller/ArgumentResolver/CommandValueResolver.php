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

use Stixx\OpenApiCommandBundle\Attribute\CommandObject;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class CommandValueResolver implements ValueResolverInterface
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $this->resolveTargetClass($request, $argument);
        if (null === $type) {
            return [];
        }

        if ($this->hasRequestBody($request)) {
            $this->assertJsonContentType($request);
            $command = $this->deserializeFromRequestBody($request, $type);
        } else {
            $data = $this->extractScalarsFromRouteAndQuery($request);

            if ($data === []) {
                throw new BadRequestHttpException('No request body provided and no mappable route/query parameters found to build the command.');
            }

            $command = $this->deserializeFromQueryParameters($data, $type);
        }

        yield $command;
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
        return $request->getContent() !== null && trim($request->getContent()) !== '';
    }

    private function assertJsonContentType(Request $request): void
    {
        $header = $request->headers->get('Content-Type', '');
        $first = (string) (current(HeaderUtils::split($header, ',')) ?: '');
        $mediaType = strtolower(trim((string) (current(HeaderUtils::split($first, ';')) ?: '')));
        $isJson = ($mediaType === 'application/json') || str_ends_with($mediaType, '+json');

        if (!$isJson) {
            throw new BadRequestHttpException('Unsupported Content-Type. Expecting application/json');
        }
    }

    /**
     * Collects scalar route attributes (excluding keys starting with "_")
     * merged with scalar query parameters; query values may override route ones.
     *
     * @return array<string, scalar|null>
     */
    private function extractScalarsFromRouteAndQuery(Request $request): array
    {
        $attributes = $request->attributes->all();
        $routeData = array_filter(
            $attributes,
            static function ($value, $key): bool {
                return is_string($key)
                    && $key !== ''
                    && $key[0] !== '_'
                    && (is_scalar($value) || $value === null);
            },
            ARRAY_FILTER_USE_BOTH
        );

        $queryData = $request->query->all();
        $filteredQuery = array_filter(
            $queryData,
            static function ($value, $key): bool {
                return is_string($key) && (is_scalar($value) || $value === null);
            },
            ARRAY_FILTER_USE_BOTH
        );

        return array_replace($routeData, $filteredQuery);
    }

    private function deserializeFromRequestBody(Request $request, string $type): object
    {
        $content = (string) $request->getContent();

        try {
            return $this->serializer->deserialize($content, $type, 'json');
        } catch (NotEncodableValueException $e) {
            throw new BadRequestHttpException('Invalid JSON body: '.$e->getMessage(), $e);
        }
    }

    /**
     * @param array<string, scalar|null> $data
     */
    private function deserializeFromQueryParameters(array $data, string $type): object
    {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        try {
            return $this->serializer->deserialize($encoded, $type, JsonEncoder::FORMAT);
        } catch (NotEncodableValueException $e) {
            throw new BadRequestHttpException('Unable to map route/query parameters to command: '.$e->getMessage(), $e);
        }
    }
}
