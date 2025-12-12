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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;

#[AutoconfigureTag(ResponderInterface::TAG_NAME)]
interface ResponderInterface
{
    public const string TAG_NAME = 'stixx_openapi_command.response.responder';

    public function respond(mixed $result, int $status): Response;

    public function supports(mixed $result): bool;
}
