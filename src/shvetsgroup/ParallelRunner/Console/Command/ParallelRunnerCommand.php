<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Console\Command;

use Behat\Behat\Console\Command\BehatCommand,
  Behat\Behat\Event\SuiteEvent;

use Symfony\Component\DependencyInjection\ContainerInterface,
  Symfony\Component\Console\Input\InputOption,
  Symfony\Component\Console\Input\InputInterface,
  Symfony\Component\Console\Output\OutputInterface,
  Symfony\Component\Process\Process;


/**
 * Behat parallel runner client console command
 *
 * @author Alexander Shvets <apang@softwaredevelopment.ca>
 */
class ParallelRunnerCommand extends BehatCommand
{
    /**
     * @var bool Number of parallel processes.
     */
    protected $processCount = 1;

    /**
     * @var array of Process classes.
     */
    protected $processes = array();

    /**
     * @var int Worker ID.
     */
    protected $workerID;

    /**
     * @var string Suite ID.
     */
    protected $suiteID;

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (isset($this->workerID)) {
            $this->runWorker();

            return $this->getCliReturnCode();
        } else {
            if ($this->processCount > 1) {
                $this->beforeSuite();
                $this->runParallel($input);
                $this->afterSuite();

                return $this->getCliReturnCode();
            } else {
                return parent::execute($input, $output);
            }
        }
    }

    /**
     * Run features in parallel.
     */
    protected function runParallel(InputInterface $input)
    {
        $eventService = $this->getContainer()->get('behat.parallel_runner.service.event');
        $this->suiteID = time() . '-' . rand(1, PHP_INT_MAX);
        $result_dir = sys_get_temp_dir() . '/behat-' . $this->suiteID;
        if (!is_dir($result_dir)) {
            mkdir($result_dir);
        }

        // Prepare parameters string.
        $command_template = array(realpath($_SERVER['SCRIPT_FILENAME']));
        foreach ($input->getArguments() as $argument) {
            $command_template[] = $argument;
        }
        foreach ($input->getOptions() as $option => $value) {
            if ($value && $option != 'parallel' && $option != 'profile') {
                $command_template[] = "--$option='$value'";
            }
        }
        if (!$input->getOption('cache')) {
            $command_template[] = "--cache='" . sys_get_temp_dir() . "/behat'";
        }
        $command_template[] = "--out='/dev/null'";

        // Spin new test processes while there are still tasks in queue.
        $this->processes = array();
        $profiles = $this->getContainer()->getParameter('parallel.profiles');
        for ($i = 0; $i < $this->processCount; $i++) {
            $command = $command_template;
            $worker_data = serialize(
                array('workerID' => $i, 'suiteID' => $this->suiteID, 'processCount' => $this->processCount)
            );
            $command[] = "--worker='$worker_data'";
            if (isset($profiles[$i])) {
                $command[] = "--profile='{$profiles[$i]}'";
            }
            $final_command = implode(' ', $command);
            $this->processes[$i] = new Process($final_command);
            $this->processes[$i]->start();
        }

        // catch app interruption
        $this->registerParentSignal();

        // Print test results while workers do the testing job.
        while (count($this->processes) > 0) {
            sleep(1);

            // Process events, dumped by children processes (in other words, do the formatting job).
            $files = glob($result_dir . '/*', GLOB_BRACE);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) == 'fatal_error') {
                    $error = unserialize(file_get_contents($file));
                    throw new \Exception(sprintf('Fatal error (workerID=%s): %s in %s on line %s', $error["workerID"], $error["message"], $error["file"], $error["line"]), E_USER_ERROR);
                }
                else {
                    $events = unserialize(file_get_contents($file));
                    $eventService->replay($events);
                    unlink($file);
                }
            }

            // Dismiss finished processes.
            foreach ($this->processes as $i => $process) {
                if (!$this->processes[$i]->isRunning()) {
                    unset($this->processes[$i]);
                }
            }
        }
        rmdir($result_dir);
    }

    /**
     * Run a single thread of testing. Stockpiles a test result exports into a temp dir.
     */
    protected function runWorker()
    {
        $this->registerWorkerSignal();

        // We don't need any formatters, but event recorder for worker process.
        $formatterManager = $this->getContainer()->get('behat.formatter.manager');
        $formatterManager->disableFormatters();
        $formatterManager->initFormatter('recorder');


        $gherkin = $this->getContainer()->get('gherkin');
        $eventService = $this->getContainer()->get('behat.parallel_runner.service.event');

        $feature_count = 1;
        foreach ($this->getFeaturesPaths() as $path) {
            // Run the tests and record the events.
            $eventService->flushEvents();
            $features = $gherkin->load((string) $path);
            foreach ($features as $feature) {
                if (($feature_count % $this->processCount) == $this->workerID) {
                    $tester = $this->getContainer()->get('behat.tester.feature');
                    $tester->setDryRun($this->isDryRun());
                    $feature->accept($tester);

                    $output_file = sys_get_temp_dir() . '/behat-' . $this->suiteID . '/' . str_replace(
                          '/',
                          '_',
                          $feature->getFile()
                      );
                    $events = $eventService->getEvents();
                    file_put_contents($output_file, serialize($events));
                    $eventService->flushEvents();
                }
                $feature_count++;
            }
        }
    }

    /**
     * Register termination handler, which correctly shuts down child processes on parent process termination.
     */
    protected function registerParentSignal()
    {
        $dispatcher = $this->getContainer()->get('behat.event_dispatcher');
        $logger = $this->getContainer()->get('behat.logger');
        $parameters = $this->getContainer()->get('behat.context.dispatcher')->getContextParameters();

        $processes = $this->processes;
        $function = function ($signal) use ($dispatcher, $parameters, $logger, $processes) {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(30);
                }
            }
            $dispatcher->dispatch('afterSuite', new SuiteEvent($logger, $parameters, false));
            throw new \Exception("Received Kill signal $signal");
        };
        pcntl_signal(SIGINT, $function);
        pcntl_signal(SIGTERM, $function);
        pcntl_signal(SIGQUIT, $function);
    }

    /**
     * Mimics default beaht's shutdown handler, but allso works on SIGTERM.
     */
    protected function registerWorkerSignal()
    {
        if (function_exists('pcntl_signal')) {
            $dispatcher = $this->getContainer()->get('behat.event_dispatcher');
            $logger = $this->getContainer()->get('behat.logger');
            $parameters = $this->getContainer()->get('behat.context.dispatcher')->getContextParameters();

            $function = function () use ($dispatcher, $parameters, $logger) {
                $dispatcher->dispatch('afterSuite', new SuiteEvent($logger, $parameters, false));
                exit(1);
            };
            pcntl_signal(SIGINT, $function);
            pcntl_signal(SIGTERM, $function);
            pcntl_signal(SIGQUIT, $function);
        }
        register_shutdown_function(array($this, "fatal_handler"));
    }

    /**
     * Register handler for fatal PHP errors.
     */
    function fatal_handler() {
        $error = error_get_last();
        if ($error['type'] == E_ERROR) {
            $error['workerID'] = $this->workerID;
            $output_file = sys_get_temp_dir() . '/behat-' . $this->suiteID . '/' . $this->workerID . '.fatal_error';
            file_put_contents($output_file, serialize($error));
        }
    }

    /**
     * @var bool Number of parallel processes.
     */
    public function setProcessCount($value)
    {
        $this->processCount = $value;
    }

    /**
     * @var bool Whether or not run the test as worker.
     */
    public function setWorkerID($value)
    {
        $this->workerID = $value;
    }

    /**
     * @var bool Whether or not run the test as worker.
     */
    public function setSuiteID($value)
    {
        $this->suiteID = $value;
    }
}