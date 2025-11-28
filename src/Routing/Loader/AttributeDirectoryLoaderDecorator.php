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
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;

final class AttributeDirectoryLoaderDecorator extends Loader
{
    public function __construct(
        private readonly AttributeDirectoryLoader $inner,
        private readonly FileLocatorInterface $locator,
        private readonly CommandRouteClassLoader $commandAttributeLoader,
        private readonly string $projectDir,
        private bool $augmented = false,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): ?RouteCollection
    {
        $collection = $this->inner->load($resource, $type) ?? new RouteCollection();

        if ($this->augmented) {
            return $collection;
        }

        $this->augmented = true;
        $projectDirectory = rtrim($this->projectDir, '/').'/src';
        $commandDirLoader = new AttributeDirectoryLoader($this->locator, $this->commandAttributeLoader);

        $commands = $commandDirLoader->load($projectDirectory, 'attribute');
        if ($commands instanceof RouteCollection) {
            $collection->addCollection($commands);
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }
}
