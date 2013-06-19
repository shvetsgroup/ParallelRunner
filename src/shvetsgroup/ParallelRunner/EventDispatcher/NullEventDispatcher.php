<?php

namespace shvetsgroup\ParallelRunner\EventDispatcher;

use \Symfony\Component\EventDispatcher\EventDispatcherInterface;
use \Symfony\Component\EventDispatcher\Event;
use \Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NullEventDispatcher implements EventDispatcherInterface {

    public function dispatch($eventName, Event $event = null)
    {
    }

    public function addListener($eventName, $listener, $priority = 0)
    {
    }

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
    }

    public function removeListener($eventName, $listener)
    {
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
    }

    public function getListeners($eventName = null)
    {
    }

    public function hasListeners($eventName = null)
    {
    }

}