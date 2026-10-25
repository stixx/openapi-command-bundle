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

use Symfony\Component\HttpFoundation\Request;

/**
 * @api
 */
final readonly class ResponseContext
{
    public function __construct(
        private Request $request,
        private object $command,
        private mixed $result,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getCommand(): object
    {
        return $this->command;
    }

    /**
     * What the command handler returned, or null when it returned nothing.
     */
    public function getResult(): mixed
    {
        return $this->result;
    }
}
