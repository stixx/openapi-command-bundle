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

use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Request;

final class CallCountValidator implements ValidatorInterface
{
    /**
     * @var list<Request>
     */
    public array $calls = [];

    public function validate(Request $request): void
    {
        $this->calls[] = $request;
    }
}
