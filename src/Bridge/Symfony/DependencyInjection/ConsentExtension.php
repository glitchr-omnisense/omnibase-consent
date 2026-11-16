<?php

namespace Base\Consent\Bridge\Symfony\DependencyInjection;

use Base\Bundle\AbstractBaseExtension;
use Base\Consent\ConsentPanel;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ConsentExtension extends AbstractBaseExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container): ConsentConfiguration
    {
        return new ConsentConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // The package root's config/ (this class is in src/Bridge/Symfony/DependencyInjection).
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 4).'/config'));
        $loader->load('services.php');

        $configuration = new ConsentConfiguration();
        $config = (new Processor())->processConfiguration($configuration, $configs);

        // Flat parameters (consent.groups, consent.countdown...), and the whole tree for the Twig extension.
        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->buildTree()->getName());
        $container->setParameter('consent.config', $config);
        // The bundle's own features, which the site's are laid over (consent.defaults).
        $container->setParameter('consent.defaults', ConsentPanel::features());
    }
}
