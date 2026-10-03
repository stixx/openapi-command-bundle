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
use Stixx\OpenApiCommandBundle\Routing\CommandRouteDiscovery;
use Stixx\OpenApiCommandBundle\Routing\Loader\RouterLoaderDecorator;
use Stixx\OpenApiCommandBundle\Routing\NelmioAreaRouteMap;
use Stixx\OpenApiCommandBundle\Validator\OpenApiSpecCache;
use Stixx\OpenApiCommandBundle\Validator\RequestValidatorInterface;
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
        if (!$this->isProblemDetailsEnabled($container->getExtensionConfig($this->getAlias()))) {
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
            ->registerForAutoconfiguration(RequestValidatorInterface::class)
            ->addTag(RequestValidatorInterface::TAG_NAME);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $this->registerCommonConfiguration($loader, $container);

        if ($commandPaths === []) {
            $container->removeDefinition(RouterLoaderDecorator::class);
            $container->removeDefinition(CommandRouteDiscovery::class);
        }

        /** @var array{problem_details: bool, warm_up: bool} $openapiConfig */
        $openapiConfig = $config['openapi'];
        if ($openapiConfig['warm_up']) {
            foreach ([OpenApiSpecCache::class, NelmioAreaRouteMap::class] as $warmer) {
                $container->getDefinition($warmer)->addTag('kernel.cache_warmer');
            }
        }
    }

    public function getAlias(): string
    {
        return Configuration::BUNDLE_ALIAS;
    }

    /**
     * Reads the raw configs: prepend() runs before the tree can be processed, and processing it here would fail on
     * required keys that are still missing.
     *
     * @param array<array<string, mixed>> $configs
     */
    private function isProblemDetailsEnabled(array $configs): bool
    {
        $enabled = true;
        foreach ($configs as $config) {
            $openapi = $config['openapi'] ?? null;
            if (is_array($openapi) && array_key_exists('problem_details', $openapi)) {
                $enabled = $openapi['problem_details'] === null || (bool) $openapi['problem_details'];
            }
        }

        return $enabled;
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
