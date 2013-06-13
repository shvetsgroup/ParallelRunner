<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Formatter;

use Behat\Behat\Formatter\ConsoleFormatter;

use Behat\Behat\Event\BackgroundEvent,
    Behat\Behat\Event\FeatureEvent,
    Behat\Behat\Event\OutlineEvent,
    Behat\Behat\Event\OutlineExampleEvent,
    Behat\Behat\Event\ScenarioEvent,
    Behat\Behat\Event\StepEvent;

/**
 * Behat Event Recorder
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class EventRecorder extends ConsoleFormatter
{
    /**
     * Event log
     *
     * @var array
     */
    private $events = array();

    /**
     * {@inheritdoc}
     */
    protected function getDefaultParameters()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $events = array(
            // subscribe to these events
            'beforeFeature', 'afterFeature',
            'beforeScenario', 'afterScenario',
            'beforeOutlineExample', 'afterOutlineExample',
            'beforeStep', 'afterStep',

            // included for completeness
            'beforeBackground', 'afterBackground',
            'beforeOutline', 'afterOutline',

            // ignored
            // 'beforeSuite', 'afterSuite',
        );

        return array_combine($events, $events);
    }

    /**
     * Listens to beforeFeature event.
     *
     * @param FeatureEvent $event
     */
    public function beforeFeature(FeatureEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to afterFeature event.
     *
     * @param FeatureEvent $event
     */
    public function afterFeature(FeatureEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to beforeScenario event.
     *
     * @param ScenarioEvent $event
     */
    public function beforeScenario(ScenarioEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to afterScenario event.
     *
     * @param ScenarioEvent $event
     */
    public function afterScenario(ScenarioEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to beforeBackground event.
     *
     * @param BackgroundEvent $event
     */
    public function beforeBackground(BackgroundEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to afterBackground event.
     *
     * @param BackgroundEvent $event
     */
    public function afterBackground(BackgroundEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to beforeOutline event.
     *
     * @param OutlineEvent $event
     */
    public function beforeOutline(OutlineEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to afterOutline event.
     *
     * @param OutlineEvent $event
     */
    public function afterOutline(OutlineEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to beforeOutlineExample event.
     *
     * @param OutlineExampleEvent $event
     */
    public function beforeOutlineExample(OutlineExampleEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to afterOutlineExample event.
     *
     * @param OutlineExampleEvent $event
     */
    public function afterOutlineExample(OutlineExampleEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to beforeStep event.
     *
     * @param StepEvent $event
     */
    public function beforeStep(StepEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Listens to afterStep event.
     *
     * @param StepEvent $event
     */
    public function afterStep(StepEvent $event)
    {
        $this->events[] = array(__FUNCTION__, $event);
    }

    /**
     * Reset formatter
     *
     * @return void
     */
    public function erase()
    {
        $this->events = array();
    }

    /**
     * Return the recorded events
     *
     * @return array
     */
    public function rip()
    {
        return $this->events;
    }
}
