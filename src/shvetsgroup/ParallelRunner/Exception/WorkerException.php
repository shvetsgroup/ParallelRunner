<?php
/**
 * @copyright 2013 Andreas Streichardt
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Exception;


/**
 * Serializeable Exception. The normal exception might contain closures in the trace which are NOT serializable
 *
 * @package shvetsgroup\ParallelRunner\Exception
 */
class WorkerException extends \Exception implements \Serializable
{
    /**
     * @return string
     */
    public function serialize()
    {
        return serialize(array($this->message, $this->code));
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        list($this->message, $this->code) = unserialize($serialized);
    }
}