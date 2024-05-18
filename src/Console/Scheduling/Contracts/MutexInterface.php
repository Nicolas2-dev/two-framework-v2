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
     * Tentative d'obtenir un mutex pour l'événement donné.
     *
     * @param  \Two\Console\Scheduling\Events\Event  $event
     * @return bool
     */
    public function create(Event $event);

    /**
     * Déterminez si un mutex existe pour l'événement donné.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return bool
     */
    public function exists(Event $event);

    /**
     * Effacez le mutex pour l'événement donné.
     *
     * @param  \Two\Console\Scheduling\Event\Event  $event
     * @return void
     */
    public function forget(Event $event);
}
