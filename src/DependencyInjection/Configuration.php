<?php

declare(strict_types=1);

namespace Stixx\OpenApiCommandBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('stixx_openapi_command');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('validate_http')
                    ->defaultTrue()
                ->end()
                ->arrayNode('validation_groups')
                    ->scalarPrototype()->end()
                    ->defaultValue(['Default'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
