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

namespace Stixx\OpenApiCommandBundle;

use Stixx\OpenApiCommandBundle\DependencyInjection\Compiler\CollectControllerClassesPass;
use Stixx\OpenApiCommandBundle\DependencyInjection\Compiler\CollectNelmioApiDocRoutesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class StixxOpenApiCommandBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CollectNelmioApiDocRoutesPass());
        $container->addCompilerPass(new CollectControllerClassesPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!isset($this->extension)) {
            $this->extension = $this->createContainerExtension();
        }

        return $this->extension;
    }
}
