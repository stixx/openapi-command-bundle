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

use Stixx\OpenApiCommandBundle\Model\ProblemDetails;
use Stixx\OpenApiCommandBundle\Model\ProblemDetailsInvalidRequestBody;
use Stixx\OpenApiCommandBundle\Model\Violation;
use Stixx\OpenApiCommandBundle\Responder\ResponderInterface;
use Stixx\OpenApiCommandBundle\Validator\ValidatorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
final class StixxOpenApiCommandExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        /** @var array{openapi: array{problem_details: bool}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        if (!$config['openapi']['problem_details']) {
            return;
        }

        $problemDetailsConfigPath = __DIR__.'/../Resources/specifications/nelmio_problem_details.yaml';
        if (!file_exists($problemDetailsConfigPath)) {
            return;
        }

        $problemDetailsConfig = Yaml::parseFile($problemDetailsConfigPath);

        if (is_array($problemDetailsConfig) && isset($problemDetailsConfig['nelmio_api_doc']) && is_array($problemDetailsConfig['nelmio_api_doc'])) {
            /** @var array<string, mixed> $nelmioConfig */
            $nelmioConfig = $problemDetailsConfig['nelmio_api_doc'];
            $container->prependExtensionConfig('nelmio_api_doc', $nelmioConfig);
        }

        $container->prependExtensionConfig('nelmio_api_doc', [
            'models' => [
                'names' => [
                    [
                        'alias' => 'ProblemDetails',
                        'type' => ProblemDetails::class,
                    ],
                    [
                        'alias' => 'Violation',
                        'type' => Violation::class,
                    ],
                    [
                        'alias' => 'ProblemDetailsInvalidRequestBody',
                        'type' => ProblemDetailsInvalidRequestBody::class,
                    ],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        /** @var array{enabled: bool, groups: list<string>} $validationConfig */
        $validationConfig = $config['validation'];

        $container->setParameter('stixx_openapi_command.validation.enabled', $validationConfig['enabled']);
        $container->setParameter('stixx_openapi_command.validation.groups', $validationConfig['groups']);
        /** @var ?string $cacheControl */
        $cacheControl = $config['cache_control'];
        $container->setParameter('stixx_openapi_command.cache_control', $cacheControl);

        /** @var list<string> $commandPaths */
        $commandPaths = $config['command_paths'];
        $container->setParameter('stixx_openapi_command.command_paths', $commandPaths);

        $container
            ->registerForAutoconfiguration(ResponderInterface::class)
            ->addTag(ResponderInterface::TAG_NAME);

        $container
            ->registerForAutoconfiguration(ValidatorInterface::class)
            ->addTag(ValidatorInterface::TAG_NAME);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $this->registerCommonConfiguration($loader, $container);
    }

    public function getAlias(): string
    {
        return Configuration::BUNDLE_ALIAS;
    }

    private function registerCommonConfiguration(PhpFileLoader $loader, ContainerBuilder $container): void
    {
        $loader->load('controller.php');
        $loader->load('response.php');
        $loader->load('routing.php');
        $loader->load('subscribers.php');
        $loader->load('validators.php');
        $loader->load('openapi.php');
        $loader->load('serializer.php');

        $container->setParameter('stixx_openapi_command.controller_classes', []);
    }
}
