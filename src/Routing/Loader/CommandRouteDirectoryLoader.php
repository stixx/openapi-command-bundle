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

namespace Stixx\OpenApiCommandBundle\Routing\Loader;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;

final class CommandRouteDirectoryLoader extends AttributeDirectoryLoader
{
    public const string TYPE = 'stixx_openapi_command.command_attributes';

    public function __construct(FileLocatorInterface $locator, CommandRouteClassLoader $loader)
    {
        parent::__construct($locator, $loader);
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }
}
