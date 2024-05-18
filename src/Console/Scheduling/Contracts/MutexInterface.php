<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling\Contracts;

use Two\Console\Scheduling\Event\Event;


interface MutexInterface
{
    /**
     * Attempt to obtain a mutex for the given event.
     *
     * @param  \Two\Console\Scheduling\Events\Event  $event
     * @return bool
     */
    public function create(Event $event);

    /**
     * Determine if a mutex exists for the given event.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return bool
     */
    public function exists(Event $event);

    /**
     * Clear the mutex for the given event.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return void
     */
    public function forget(Event $event);
}
