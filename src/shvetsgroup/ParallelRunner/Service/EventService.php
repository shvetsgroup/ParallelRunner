<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Service;

use Symfony\Component\EventDispatcher\EventDispatcher;

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
     * Constructor
     *
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(EventDispatcher $eventDispatcher)
    {
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
        foreach ($events as $eventTuple) {
            list($name, $event) = $eventTuple;

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
                    $this->eventDispatcher->dispatch($name, $event);
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
        return $this->getEventRecorder()->rip();
    }
}
