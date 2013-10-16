<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Console\Command;

use Behat\Behat\Console\Command\BehatCommand, Behat\Behat\Event\SuiteEvent;

use Symfony\Component\DependencyInjection\ContainerInterface, Symfony\Component\Console\Input\InputOption,
  Symfony\Component\Console\Input\InputInterface, Symfony\Component\Console\Output\OutputInterface,
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
     * @var array List of features tested by workers.
     */
    protected $testedByWorker;

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
                $this->runParallel($input, $output);

                return $this->getCliReturnCode();
            } else {
                return parent::execute($input, $output);
            }
        }
    }

    /**
     * Run features in parallel.
     */
    protected function runParallel(InputInterface $input, OutputInterface $output)
    {
        $this->beforeSuite();

        $this->setSuiteID(time() . '-' . rand(1, PHP_INT_MAX));
        $this->createTestPlan();
        $this->startWorkers($input, $output);
        $this->processTestResults();

        $this->afterSuite();
    }

    /**
     * Creates a list of files, one per a feature to test. These files will serve as a task list for test workers.
     * Each file will be removed once worker has taken it to work.
     */
    protected function createTestPlan()
    {
        $gherkin = $this->getContainer()->get('gherkin');
        foreach ($this->getFeaturesPaths() as $path) {
            $features = $gherkin->load((string) $path);
            foreach ($features as $feature) {
                $output_file = $this->getTestDir() . '/plan/' . str_replace('/', '_', $feature->getFile());
                file_put_contents($output_file, $feature->getFile());
            }
        }
    }

    /**
     * Start worker processes, using all console prameters, that client process got.
     *
     * @param InputInterface $input
     */
    protected function startWorkers(InputInterface $input, OutputInterface $output)
    {
        $this->registerParentSignal();

        // Prepare parameters string.
        $command_template = array('XDEBUG_CONFIG="idekey=worker"  ' . realpath($_SERVER['SCRIPT_FILENAME']));
        foreach ($input->getArguments() as $argument) {
            $command_template[] = $argument;
        }
        $definition = $this->getDefinition();
        foreach ($input->getOptions() as $option => $value) {
            if ($value && $option != 'parallel' && $option != 'profile') {
                if ($definition->getOption($option)->acceptValue()) {
                    $command_template[] = "--$option='$value'";
                } else {
                    $command_template[] = "--$option";
                }
            }
        }
        if (!$input->getOption('cache')) {
            $command_template[] = "--cache='" . sys_get_temp_dir() . "/behat'";
        }
        $command_template[] = "--out='/dev/null'";

        // Spin new testing processes.
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
    }

    /**
     * Scan the workers' test results and output them in combined way.
     *
     * @throws \Exception
     */
    protected function processTestResults()
    {
        $eventService = $this->getContainer()->get('behat.parallel_runner.service.event');
        while (count($this->processes) > 0) {
            sleep(1);

            // Dismiss finished processes.
            foreach ($this->processes as $i => $process) {
                if (!$this->processes[$i]->isRunning()) {
                    unset($this->processes[$i]);
                }
            }

            // Process events, dumped by children processes (in other words, do the formatting job).
            $files = glob($this->getTestDir() . '/results/*', GLOB_BRACE);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) == 'fatal_error') {
                    $error = unserialize(file_get_contents($file));
                    throw new \Exception(sprintf(
                        'Fatal error (workerID=%s): %s in %s on line %s',
                        $error["workerID"],
                        $error["message"],
                        $error["file"],
                        $error["line"]
                    ), E_USER_ERROR);
                } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'exception') {
                    $error = file_get_contents($file);
                    throw new \Exception($error, E_USER_ERROR);
                } else {
                    $events = unserialize(file_get_contents($file));
                    $eventService->replay($events);
                    unlink($file);
                }
            }
        }
        $this->removeTestDir();
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

        while ($feature_path = $this->getFeatureFromTestPlan()) {
            $eventService->flushEvents();
            $features = $gherkin->load((string) $feature_path);
            foreach ($features as $feature) {
                try {
                    $tester = $this->getContainer()->get('behat.tester.feature');
                    $tester->setSkip($this->isDryRun());
                    $feature->accept($tester);

                    $output_file = $this->getTestDir() . '/results/' . str_replace('/', '_', $feature->getFile());
                    $events = $eventService->getEvents();
                    file_put_contents($output_file, serialize($events));
                    $eventService->flushEvents();
                } catch (\Exception $e) {
                    $this->exceptionHandler($e);
                }
            }
        }
    }

    /**
     * Scans the test plan and returns the path to an untested feature.
     *
     * @return bool|string
     */
    protected function getFeatureFromTestPlan()
    {
        $test_plan = glob($this->getTestDir() . '/plan/*', GLOB_BRACE);
        foreach ($test_plan as $test_job) {
            if (is_file($test_job)) {
                $fp = @fopen($test_job, "c+");
                if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
                    $feature_path = fread($fp, filesize($test_job));
                    ftruncate($fp, 0);
                    fclose($fp);
                    unlink($test_job);
                    if (is_file($feature_path) && !isset($this->testedByWorker[$feature_path])) {
                        $this->testedByWorker[$feature_path] = true;

                        return $feature_path;
                    }
                }
            }
        }

        return false;
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
        set_exception_handler(array($this, "exceptionHandler"));
        register_shutdown_function(array($this, "fatalErrorHandler"));
    }

    /**
     * Creates a special file in test directory, which contains a description about fatal error in worker. This
     * fatal error then replayed as exception in the main thread.
     */
    function fatalErrorHandler()
    {
        $error = error_get_last();
        if ($error['type'] == E_ERROR) {
            $error['workerID'] = $this->workerID;
            $output_file = $this->getTestDir() . '/results/' . $this->workerID . '.fatal_error';
            file_put_contents($output_file, serialize($error));
        }
    }

    /**
     * Creates a special file in test directory, which contains an exception message.
     */
    function exceptionHandler($exception)
    {
        $output_file = $this->getTestDir() . '/results/' . $this->workerID . '.exception';
        file_put_contents($output_file, "Worker exception (workerID={$this->workerID}): " . $exception->getMessage());
    }

    /**
     * @return string The full path to temporary directory, which contains the test plan and results from workers.
     */
    protected function getTestDir()
    {
        $test_dir = sys_get_temp_dir() . '/behat-' . $this->suiteID;
        if (!is_dir($test_dir)) {
            mkdir($test_dir);
        }
        if (!is_dir($test_dir . '/results')) {
            mkdir($test_dir . '/results');
        }
        if (!is_dir($test_dir . '/plan')) {
            mkdir($test_dir . '/plan');
        }

        return $test_dir;
    }

    /**
     * Removes all testing directories after the test.
     */
    function removeTestDir($dir = null)
    {
        if (is_null($dir)) {
            $dir = $this->getTestDir();
        }
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        $this->removeTestDir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
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