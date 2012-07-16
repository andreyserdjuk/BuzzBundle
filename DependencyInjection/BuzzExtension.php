<?php

namespace Buzz\Bundle\BuzzBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BuzzExtension extends Extension
{
    /**
     * @inheritdoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('buzz.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $listeners = $this->loadListenersSection($config['listeners'], $container);
        $this->loadBrowsersSection($config['browsers'], $listeners, $container);

        if ($config['profiler']) {
            $this->loadProfiler(array_keys($config['browsers']), $container);
        }

        $container->setParameter('buzz', $config);

        return $config;
    }

    private function loadListenersSection(array $config, ContainerBuilder $container)
    {
        $listeners = array();
        foreach ($config as $key => $listener) {
            $listeners[$key] = new Reference($listener['id']);
        }

        return $listeners;
    }

    private function loadBrowsersSection(array $config, array $listeners, ContainerBuilder $container)
    {
        foreach ($config as $name => $browserConfig) {
            $browser = $this->createBrowser($name, $browserConfig, $container);
            $this->configureBrowser($browser, $browserConfig, $listeners);
         }
    }

    private function configureBrowser(Definition $browser, array $browserConfig, array $listeners)
    {
        foreach ($browserConfig['listeners'] as $listener) {
            $browser->addMethodCall('addListener', array($listeners[$listener]));
        }
    }

    private function createBrowser($name, array $config, ContainerBuilder $container)
    {
        $browser = 'buzz.browser.'.$name;

        $container->register($browser, 'Buzz\Browser')
            ->setArguments(array(null, null))
        ;

        $container->getDefinition('buzz.browser_manager')
            ->addMethodCall('set', array($name, new Reference($browser)))
        ;

        $browser = $container->getDefinition($browser);

        if (isset($config['client']) && !empty($config['client'])) {
            $browser
                ->replaceArgument(0, new Reference('buzz.client.'.$config['client']))
                ->replaceArgument(1, null)
            ;
        }

        if (!empty($config['host'])) {
            $listener = 'buzz.listener.host_'.$name;

            $container
                ->register($listener, 'Buzz\Bundle\BuzzBundle\Buzz\Listener\HostListener')
                ->addArgument($config['host'])
            ;

            $browser->addMethodCall('addListener', array(new Reference($listener)));
        }

        return $browser;
    }

    private function loadProfiler(array $browserNames, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('datacollector.xml');

        foreach($browserNames as $name) {
            $container->getDefinition('buzz.browser.'.$name)
                ->addMethodCall('addListener', array(new Reference('buzz.listener.history')))
            ;

        }
    }
}
