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

namespace Stixx\OpenApiCommandBundle\Response;

use OpenApi\Annotations\Operation;
use OpenApi\Attributes as OAT;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

final class ResponseStatusResolver implements StatusResolverInterface
{
    public function resolve(Request $request, object $command): int
    {
        $method = strtoupper($request->getMethod());
        $operation = $this->findOperationAttributeForMethod($command, $method);

        if ($operation !== null) {
            $status = $this->first2xxFromOperation($operation);

            if ($status !== null) {
                return $status;
            }
        }

        return match ($method) {
            Request::METHOD_POST => 201,
            Request::METHOD_DELETE => 204,
            default => 200,
        };
    }

    private function findOperationAttributeForMethod(object $command, string $method): ?Operation
    {
        $class = new ReflectionClass($command);

        $map = [
            'GET' => OAT\Get::class,
            'POST' => OAT\Post::class,
            'PUT' => OAT\Put::class,
            'PATCH' => OAT\Patch::class,
            'DELETE' => OAT\Delete::class,
            'OPTIONS' => OAT\Options::class,
            'HEAD' => OAT\Head::class,
            'TRACE' => OAT\Trace::class,
        ];

        $target = $map[$method] ?? null;
        if ($target === null) {
            return null;
        }

        $attrs = $class->getAttributes($target, ReflectionAttribute::IS_INSTANCEOF);
        $attr = $attrs[0] ?? null;
        $operation = $attr?->newInstance();

        if (!$operation instanceof Operation) {
            return null;
        }

        return $operation;
    }

    private function first2xxFromOperation(Operation $operation): ?int
    {
        $responses = $operation->responses ?? [];

        foreach ($responses as $response) {
            $code = $response->response ?? null;
            if ($code === null) {
                continue;
            }

            if (is_string($code) && ctype_digit($code)) {
                $code = (int) $code;
            }

            if (is_int($code) && $code >= 200 && $code < 300) {
                return $code;
            }
        }

        return null;
    }
}
