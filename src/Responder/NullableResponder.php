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

/**
 * @internal
 */
final readonly class NullableResponder implements ResponderInterface
{
    public function respond(ResponseContext $context, int $status): Response
    {
        return new Response(null, $status);
    }

    public function supports(ResponseContext $context): bool
    {
        return $context->getResult() === null;
    }
}
