<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition,
  Symfony\Component\DependencyInjection\ContainerBuilder,
  Symfony\Component\DependencyInjection\Reference;

use Behat\Behat\Extension\ExtensionInterface;


class Extension implements ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $container->setParameter(
            'behat.console.command.class',
            '\shvetsgroup\ParallelRunner\Console\Command\ParallelRunnerCommand'
        );
        $container
          ->register(
              'behat.parallel_runner.console.processor.parallel',
              '\shvetsgroup\ParallelRunner\Console\Processor\ParallelProcessor'
          )
          ->addArgument(new Reference('service_container'))
          ->addTag('behat.console.processor');
        $container
          ->register('behat.formatter.dispatcher.recorder', '\Behat\Behat\Formatter\FormatterDispatcher')
          ->addArgument('shvetsgroup\ParallelRunner\Formatter\EventRecorder')
          ->addArgument('recorder')
          ->addArgument('Event recorder for events handled by a Gearman worker.')
          ->addTag('behat.formatter.dispatcher');
        $container
          ->register('behat.parallel_runner.service.event', '\shvetsgroup\ParallelRunner\Service\EventService')
          ->addArgument(new Reference('service_container'))
          ->addArgument(new Reference('behat.event_dispatcher'));

        $container->setParameter('parallel.process_count', $config['process_count']);
        $container->setParameter('parallel.profiles', $config['profiles']);
    }

    /**
     * Setup configuration for this extension.
     *
     * @param ArrayNodeDefinition $builder
     *   ArrayNodeDefinition instance.
     */
    public function getConfig(ArrayNodeDefinition $builder)
    {
        $builder
          ->children()
              ->scalarNode('process_count')
                  ->defaultValue(1)
              ->end()
              ->arrayNode('profiles')
                  ->defaultValue(array())
                  ->prototype('scalar')
                  ->end()
              ->end()
          ->end();
    }

    /**
     * Returns compiler passes used by mink extension.
     *
     * @return array
     */
    public function getCompilerPasses()
    {
        return array();
    }
}

return new Extension();