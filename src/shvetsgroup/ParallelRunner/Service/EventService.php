<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Service;

use Behat\Behat\Event\OutlineExampleEvent;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Behat\Event\StepEvent;
use shvetsgroup\ParallelRunner\Context\NullContext;
use shvetsgroup\ParallelRunner\EventDispatcher\NullEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher,
  Symfony\Component\DependencyInjection\ContainerInterface;

use shvetsgroup\ParallelRunner\Formatter\EventRecorder;

/**
 * Event service
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class EventService
{
    /**
     * @var EventRecorder
     */
    private $eventRecorder;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Constructor
     *
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(ContainerInterface $container, EventDispatcher $eventDispatcher)
    {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Find event recorder
     *
     * @return EventRecorder|null
     */
    private function findEventRecorder()
    {
        $listeners = $this->eventDispatcher->getListeners('beforeFeature');

        foreach ($listeners as $listener) {
            $formatter = isset($listener[0]) ? $listener[0] : null;

            if (is_object($formatter) && get_class($formatter) === 'shvetsgroup\ParallelRunner\Formatter\EventRecorder') {
                return $formatter;
            }
        }

        return null;
    }

    /**
     * Get event recorder
     *
     * @return EventRecorder|null
     */
    private function getEventRecorder()
    {
        if (!$this->eventRecorder) {
            $this->eventRecorder = $this->findEventRecorder();
        }

        return $this->eventRecorder;
    }

    /**
     * Playback recorded events
     *
     * @param array $events
     */
    public function replay($events)
    {
        foreach ($events as $eventTurple) {
            list($name, $event) = $eventTurple;
            $event->replay = true;

            switch ($name) {
                // subscribe to these events
                case 'beforeFeature':
                case 'afterFeature':
                case 'beforeScenario':
                case 'afterScenario':
                case 'beforeOutlineExample':
                case 'afterOutlineExample':
                case 'beforeStep':
                case 'afterStep':

                    // included for completeness
                case 'beforeBackground':
                case 'afterBackground':
                case 'beforeOutline':
                case 'afterOutline':
                    $this->getContainer()->get('behat.hook.dispatcher')->setDryRun();
                    $this->eventDispatcher->dispatch($name, $event);
                    $this->getContainer()->get('behat.hook.dispatcher')->setDryRun(FALSE);
                    break;

                // ignored
                case 'beforeSuite':
                case 'afterSuite':
                default:
                    // do nothing
            }
        }
    }

    /**
     * Reset event recorder
     *
     * @return void
     */
    public function flushEvents()
    {
        $this->getEventRecorder()->erase();
    }

    /**
     * Return recorded events
     *
     * @return array
     */
    public function getEvents()
    {
        $events = $this->getEventRecorder()->rip();

        foreach ($events as $key => $eventTurple) {
            list($name, $event) = $eventTurple;

            if ($event instanceof StepEvent) {
                $event = new StepEvent(
                    $event->getStep(),
                    $event->getLogicalParent(),
                    new NullContext(),
                    $event->getResult(),
                    null,
                    $event->getException() ? new \Exception($event->getException()->getMessage()) : null,
                    $event->getSnippet()
                );
            }

            if ($event instanceof OutlineExampleEvent) {
                $event = new OutlineExampleEvent(
                    $event->getOutline(),
                    $event->getIteration(),
                    new NullContext(),
                    $event->getResult(),
                    $event->isSkipped()
                );
            }

            if ($event instanceof ScenarioEvent) {
                $event = new ScenarioEvent(
                    $event->getScenario(),
                    new NullContext(),
                    $event->getResult(),
                    $event->isSkipped()
                );
            }

            $event->setDispatcher(new NullEventDispatcher());

            $events[$key] = array($name, $event);
        }

        return $events;
    }

    /**
     * Returns container instance.
     *
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        return $this->container;
    }
}
