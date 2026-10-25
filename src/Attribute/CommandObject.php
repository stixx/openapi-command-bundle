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

/**
 * On a controller parameter, marks the argument the request is mapped to; $class overrides the parameter's type.
 * On a command class, $controller replaces the bundle's controller for each of its operations.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_CLASS)]
final readonly class CommandObject
{
    /**
     * @param class-string|null $class
     * @param string|null $controller a controller class, Class::method or service id
     */
    public function __construct(
        public ?string $class = null,
        public ?string $controller = null,
    ) {
    }
}
