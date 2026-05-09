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

use OpenApi\Attributes as OA;

/**
 * @api
 */
#[OA\Schema(
    required: [
        'propertyPath',
        'message',
        'code',
        'constraint',
    ]
)]
final class Violation
{
    public function __construct(
        public string $propertyPath,
        public string $message,
        public string $code,
        public string $constraint,
        public ?string $error,
    ) {
    }
}
