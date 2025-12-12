<?php

declare(strict_types=1);

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
