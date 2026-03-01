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

namespace Stixx\OpenApiCommandBundle\Model;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'InvalidRequestBody',
    properties: [
        new OA\Property(property: 'violations', type: 'array', items: new OA\Items(ref: new Model(type: Violation::class))),
    ],
    example: new OA\Schema(
        title: 'The request body contains errors.',
        type: 'about:blank',
    )
)]
final class ProblemDetailsInvalidRequestBody extends ProblemDetails
{
}
