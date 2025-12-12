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

use Symfony\Component\HttpFoundation\Response;

final class NullableResponder implements ResponderInterface
{
    public function respond(mixed $result, int $status): Response
    {
        return new Response(null, $status);
    }

    public function supports(mixed $result): bool
    {
        return empty($result);
    }
}
