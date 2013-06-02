<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Console\Processor;

use Behat\Behat\Exception\FormatterException,
  Behat\Behat\Console\Processor\Processor;
use Symfony\Component\DependencyInjection\ContainerInterface,
  Symfony\Component\Console\Command\Command,
  Symfony\Component\Console\Input\InputInterface,
  Symfony\Component\Console\Input\InputOption,
  Symfony\Component\Console\Output\OutputInterface;

/**
 * Parallel runner parameters processor.
 */
class ParallelProcessor extends Processor
{
    private $container;

    /**
     * Constructs processor.
     *
     * @param ContainerInterface $container Container instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Configures command to be able to process it later.
     *
     * @param Command $command
     */
    public function configure(Command $command)
    {
        $command
          ->addOption(
              '--parallel',
              '-l',
              InputOption::VALUE_REQUIRED,
              "Specify number of parallel processes to run tests with"
          )
          ->addOption(
              '--worker',
              '-w',
              InputOption::VALUE_REQUIRED,
              "Run test as a worker with following data (for internal use only)"
          );
    }

    /**
     * Processes data from container and console input.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws FormatterException
     */
    public function process(InputInterface $input, OutputInterface $output)
    {
        $command = $this->container->get('behat.console.command');
        if ($input->getOption('worker')) {
            $data = unserialize($input->getOption('worker'));
            $command->setWorkerID($data['workerID']);
            $command->setSuiteID($data['suiteID']);
            $command->setProcessCount($data['processCount']);
        } else {
            if ($input->getOption('parallel')) {
                if (is_numeric($input->getOption('parallel'))) {
                    $command->setProcessCount($input->getOption('parallel'));
                } else {
                    throw new FormatterException("Option '--parallel' should be numeric.");
                }
            } else {
                if ($this->container->getParameter('parallel.process_count')) {
                    $command->setProcessCount($this->container->getParameter('parallel.process_count'));
                }
            }
        }
    }
}
