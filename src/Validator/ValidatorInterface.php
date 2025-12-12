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

namespace Stixx\OpenApiCommandBundle\Validator;

use Symfony\Component\HttpFoundation\Request;

interface ValidatorInterface
{
    public const string TAG_NAME = 'stixx_openapi_command.request.validator';

    public function validate(Request $request): void;
}
