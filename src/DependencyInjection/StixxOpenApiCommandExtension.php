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

namespace Stixx\OpenApiCommandBundle\DependencyInjection;

use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class StixxOpenApiCommandExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        /** @var array{enabled: bool, groups: list<string>} $validationConfig */
        $validationConfig = $config['validation'];

        $container->setParameter('stixx_openapi_command.validation.enabled', $validationConfig['enabled']);
        $container->setParameter('stixx_openapi_command.validation.groups', $validationConfig['groups']);

        $container
            ->registerForAutoconfiguration(ResponderInterface::class)
            ->addTag(ResponderInterface::TAG_NAME);

        $container
            ->registerForAutoconfiguration(ValidatorInterface::class)
            ->addTag(ValidatorInterface::TAG_NAME);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $this->registerCommonConfiguration($loader);
    }

    public function getAlias(): string
    {
        return Configuration::BUNDLE_ALIAS;
    }

    private function registerCommonConfiguration(PhpFileLoader $loader): void
    {
        $loader->load('controller.php');
        $loader->load('response.php');
        $loader->load('routing.php');
        $loader->load('subscribers.php');
        $loader->load('validators.php');
        $loader->load('openapi.php');
        $loader->load('serializer.php');
    }
}
