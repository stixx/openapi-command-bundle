<?php

declare(strict_types=1);

namespace Stixx\OpenApiCommandBundle\DependencyInjection;

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

        $container->setParameter('stixx_openapi_command.validate_http', $config['validate_http']);
        $container->setParameter('stixx_openapi_command.validation_groups', $config['validation_groups']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $this->registerCommonConfiguration($loader);
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
