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

namespace Stixx\OpenApiCommandBundle\Tests\Mock\Command;

use OpenApi\Attributes as OA;

#[OA\Get(
    responses: [
        new OA\Response(response: 400),
        new OA\Response(response: 500),
    ]
)]
final class GetWithNon2xxResponsesCommand
{
}
