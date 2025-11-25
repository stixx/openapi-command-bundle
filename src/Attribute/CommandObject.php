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

namespace Stixx\OpenApiCommandBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CommandObject
{
    public function __construct(
        public ?string $class = null
    ) {
    }
}
