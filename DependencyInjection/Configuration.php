<?php

namespace Bloghoven\Bundle\JekyllProviderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('bloghoven_jekyll_provider');

        $rootNode
            ->children()
                ->scalarNode('filesystem')->defaultValue(null)->end()
                ->scalarNode('file_extension')->defaultValue('md')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
