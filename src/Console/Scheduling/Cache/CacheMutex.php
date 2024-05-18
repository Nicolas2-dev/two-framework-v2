<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling\Cache;

use Two\Cache\Repository as Cache;
use Two\Console\Scheduling\Event\Event;
use Two\Console\Scheduling\Contracts\MutexInterface;


class CacheMutex implements MutexInterface
{
    /**
     * The cache repository implementation.
     *
     * @var \Two\Cache\Repository
     */
    public $cache;


    /**
     * Create a new overlapping strategy.
     *
     * @param  \Two\Cache\Repository  $cache
     * @return void
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Attempt to obtain a mutex for the given event.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return bool
     */
    public function create(Event $event)
    {
        return $this->cache->add(
            $event->mutexName(), true, $event->expiresAt
        );
    }

    /**
     * Determine if a mutex exists for the given event.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return bool
     */
    public function exists(Event $event)
    {
        return $this->cache->has($event->mutexName());
    }

    /**
     * Clear the mutex for the given event.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return void
     */
    public function forget(Event $event)
    {
        $this->cache->forget($event->mutexName());
    }
}
