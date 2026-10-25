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
#[OA\Schema(required: ['propertyPath', 'message'])]
final class Violation
{
    public function __construct(
        #[OA\Property(description: 'Path of the invalid value; empty when the violation concerns the whole request or command.')]
        public readonly string $propertyPath,
        #[OA\Property(description: 'A human-readable explanation of the violation.')]
        public readonly string $message,
        #[OA\Property(description: 'Machine-readable code: the Symfony constraint error code, or the failed JSON Schema keyword.')]
        public readonly ?string $code = null,
        #[OA\Property(description: 'Short name of the Symfony constraint, or "openapi_request_validation".')]
        public readonly ?string $constraint = null,
        #[OA\Property(description: 'Name of the Symfony constraint error constant, e.g. IS_BLANK_ERROR.')]
        public readonly ?string $error = null,
    ) {
    }
}
