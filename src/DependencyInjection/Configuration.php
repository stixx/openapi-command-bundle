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

use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public const string BUNDLE_ALIAS = 'stixx_openapi_command';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::BUNDLE_ALIAS);

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('validation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('groups')
                            ->scalarPrototype()->end()
                            ->defaultValue(['Default'])
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('cache_control')
                    ->defaultValue('no-store')
                ->end()
                ->arrayNode('command_paths')
                    ->info('Directories or glob patterns holding command DTOs, e.g. ["%kernel.project_dir%/src/Command"] or ["%kernel.project_dir%/src/*/Application/Command"]; [] disables discovery')
                    ->isRequired()
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                        ->validate()
                            ->ifTrue(static fn (mixed $path): bool => is_string($path) && str_ends_with(strtolower($path), '.php'))
                            ->then(static fn (string $path): never => throw new InvalidArgumentException(sprintf('Entries must match directories, not files: remove the file pattern from "%s".', $path)))
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('openapi')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('problem_details')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
