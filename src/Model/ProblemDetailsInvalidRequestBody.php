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
        new OA\Property(property: 'violations', type: 'array', items: new OA\Items(ref: new Model(type: Violation::class)), maxItems: 100),
    ],
    example: [
        'type' => 'about:blank',
        'title' => 'The request body contains errors.',
        'status' => 400,
        'detail' => 'Validation failed.',
        'violations' => [
            [
                'propertyPath' => 'foo',
                'message' => 'This value should not be blank.',
                'code' => 'c1ac8c7d-eab5-458f-a950-fcf121d23059',
                'constraint' => 'NotBlank',
            ],
        ],
    ]
)]
final class ProblemDetailsInvalidRequestBody extends ProblemDetails
{
}
