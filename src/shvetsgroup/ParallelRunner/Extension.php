<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner;

use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition,
  Symfony\Component\DependencyInjection\ContainerBuilder,
  Symfony\Component\DependencyInjection\Reference;

use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;

class Extension implements ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
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
          ->addArgument(new Reference('event_dispatcher'));

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
              ->integerNode('process_count')
                  ->min(1)
                  ->defaultValue(1)
                  ->end()
              ->scalarNode('profiles')
              ->defaultValue([])
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
        return [];
    }

  /**
   * You can modify the container here before it is dumped to PHP code.
   *
   * @param ContainerBuilder $container
   */
  public function process(ContainerBuilder $container) {
    // TODO: Implement process() method.
  }

  /**
   * Returns the extension config key.
   *
   * @return string
   */
  public function getConfigKey() {
    return 'ParallelRunner';
  }

  /**
   * Initializes other extensions.
   *
   * This method is called immediately after all extensions are activated but
   * before any extension `configure()` method is called. This allows extensions
   * to hook into the configuration of other extensions providing such an
   * extension point.
   *
   * @param ExtensionManager $extensionManager
   */
  public function initialize(ExtensionManager $extensionManager) {
    // TODO: Implement initialize() method.
  }

  /**
   * Setups configuration for the extension.
   *
   * @param ArrayNodeDefinition $builder
   */
  public function configure(ArrayNodeDefinition $builder) {
    // TODO: Implement configure() method.
    $builder
      ->children()
      ->integerNode('process_count')
      ->min(1)
      ->defaultValue(1)
      ->end()
      ->scalarNode('profiles')
      ->defaultValue([])
      ->end()
      ->end();
  }
}

return new Extension();
