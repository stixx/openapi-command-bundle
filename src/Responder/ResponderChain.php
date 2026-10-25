<?php

/*
 * This file is part of the StixxOpenApiCommandBundle package.
 *
 * (c) Stixx
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stixx\OpenApiCommandBundle\Responder;

use OutOfBoundsException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final readonly class ResponderChain
{
    /**
     * @param iterable<ResponderInterface> $responders
     */
    public function __construct(private iterable $responders)
    {
    }

    public function respond(ResponseContext $context, int $status): Response
    {
        foreach ($this->responders as $responder) {
            if (!$responder->supports($context)) {
                continue;
            }

            return $responder->respond($context, $status);
        }

        throw new OutOfBoundsException('No supported responder found.');
    }
}
