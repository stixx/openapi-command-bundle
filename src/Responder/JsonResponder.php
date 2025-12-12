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

final class JsonResponder implements ResponderInterface
{
    public function respond(mixed $result, int $status): Response
    {
        return new JsonResponse($result, $status);
    }

    public function supports(mixed $result): bool
    {
        return $result instanceof JsonSerializable;
    }
}
