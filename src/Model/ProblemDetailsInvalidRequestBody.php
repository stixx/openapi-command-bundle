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

/**
 * @api
 */
#[OA\Schema(
    title: 'InvalidRequestBody',
    required: ['type', 'title', 'status', 'violations'],
    example: [
        'type' => 'about:blank',
        'title' => 'Unprocessable Content',
        'status' => 422,
        'detail' => 'Validation failed.',
        'violations' => [
            [
                'propertyPath' => 'title',
                'message' => 'This value should not be blank.',
                'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                'constraint' => 'NotBlank',
                'error' => 'IS_BLANK_ERROR',
            ],
        ],
    ]
)]
class ProblemDetailsInvalidRequestBody extends ProblemDetails
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        int $status,
        string $title,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Violation::class)))]
        public readonly array $violations,
        ?string $detail = null,
        string $type = 'about:blank',
        ?string $instance = null,
    ) {
        parent::__construct($status, $title, $detail, $type, $instance);
    }
}
