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

namespace Stixx\OpenApiCommandBundle\Tests\Mock;

use OpenApi\Attributes as OA;

#[OA\Post(
    responses: [
        new OA\Response(response: 201),
        new OA\Response(response: 200),
    ]
)]
final class PostWithResponsesCommand
{
}
