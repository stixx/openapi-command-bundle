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

namespace Stixx\OpenApiCommandBundle\Responder;

use JsonSerializable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class JsonSerializedResponder implements ResponderInterface
{
    public function __construct(private SerializerInterface $serializer)
    {
    }

    public function respond(mixed $result, int $status): Response
    {
        $json = $this->serializer->serialize($result, JsonEncoder::FORMAT);

        return JsonResponse::fromJsonString($json, $status);
    }

    public function supports(mixed $result): bool
    {
        return is_object($result) && !$result instanceof JsonSerializable;
    }
}
