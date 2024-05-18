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
     * L'implémentation du référentiel de cache.
     *
     * @var \Two\Cache\Repository
     */
    public $cache;


    /**
     * Créez une nouvelle stratégie de chevauchement.
     *
     * @param  \Two\Cache\Repository  $cache
     * @return void
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Tentative d'obtenir un mutex pour l'événement donné.
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
     * Déterminez si un mutex existe pour l'événement donné.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return bool
     */
    public function exists(Event $event)
    {
        return $this->cache->has($event->mutexName());
    }

    /**
     * Effacez le mutex pour l'événement donné.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return void
     */
    public function forget(Event $event)
    {
        $this->cache->forget($event->mutexName());
    }
}
