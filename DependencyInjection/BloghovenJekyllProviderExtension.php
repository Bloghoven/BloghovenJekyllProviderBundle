<?php

namespace Bloghoven\Bundle\JekyllProviderBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Definition;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class BloghovenJekyllProviderExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $this->setAdapter($container, $config);
        $this->updateProviderFileExtension($container, $config);
    }

    protected function setAdapter(ContainerBuilder $container, $config)
    {
        if (isset($config['filesystem']))
        {
            $container->setAlias('bloghoven.jekyll_provider.filesystem', $config['filesystem']);
        }
    }

    protected function updateProviderFileExtension(ContainerBuilder $container, $config)
    {
        $def = $container->getDefinition('bloghoven.jekyll_provider.content_provider');
        $def->replaceArgument(1, $config['file_extension']);
    }
}
