<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Service;

use Symfony\Component\EventDispatcher\EventDispatcher,
  Symfony\Component\DependencyInjection\ContainerInterface,
  Symfony\Component\Serializer\Serializer,
  Symfony\Component\Serializer\Encoder\JsonEncoder;

use shvetsgroup\ParallelRunner\Formatter\EventRecorder,
  shvetsgroup\ParallelRunner\Normalizer\EventNormalizer;

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
     * @var EventNormalizer
     */
    private $eventNormalizer;

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
        $this->eventNormalizer = new EventNormalizer();
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
        $serializer = $this->getSerializer();

        foreach ($events as $eventInfo) {
            list($name, $event_class, $event_json) = $eventInfo;
            $event = $serializer->deserialize($event_json, $event_class, 'json', array('container' => $this->getContainer()));
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
        $serializer = $this->getSerializer();
        $events = $this->getEventRecorder()->rip();

        foreach ($events as $key => $eventTuple) {
            list($name, $event) = $eventTuple;
            $event_class = get_class($event);
            $event_json = $serializer->serialize($event, 'json', array('container' => $this->getContainer()));
            $events[$key] = array($name, $event_class, $event_json);
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

    /**
     * @return Serializer
     */
    protected function getSerializer()
    {
        $encoder = new JsonEncoder();
        $normalizer = new EventNormalizer();
        $normalizer->setCallbacks(
            array(
                'dispatcher' => array($normalizer, 'normalizeEmpty'),
                'context' => array($normalizer, 'normalizeEmpty'),
                'feature' => array($normalizer, 'normalizeFeature'),
                'definition' => array($normalizer, 'normalizeNull'),
                'scenario' => array($normalizer, 'normalizeNode'),
                'outline' => array($normalizer, 'normalizeNode'),
                'background' => array($normalizer, 'normalizeNode'),
                'parent' => array($normalizer, 'normalizeNode'),
                'logicalParent' => array($normalizer, 'normalizeNode'),
                'step' => array($normalizer, 'normalizeNode'),
                'exception' => array($normalizer, 'normalizeException'),
                'trace' => array($normalizer, 'normalizeNull'),
            )
        );
        $serializer = new Serializer(array($normalizer), array($encoder));

        return $serializer;
    }
}
