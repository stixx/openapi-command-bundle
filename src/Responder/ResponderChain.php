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

final readonly class ResponderChain implements ResponderInterface
{
    /**
     * @param iterable<ResponderInterface> $responders
     */
    public function __construct(private iterable $responders)
    {
    }

    public function respond(mixed $result, int $status): Response
    {
        foreach ($this->responders as $responder) {
            if (!$responder->supports($result)) {
                continue;
            }

            return $responder->respond($result, $status);
        }

        throw new OutOfBoundsException('No supported responder found.');
    }

    public function supports(mixed $result): bool
    {
        return false;
    }
}
