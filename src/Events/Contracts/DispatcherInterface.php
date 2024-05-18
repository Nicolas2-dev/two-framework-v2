<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Events\Contracts;


interface DispatcherInterface
{

    /**
     * Enregistrez un écouteur d'événement auprès du répartiteur.
     *
     * @param  string|array  $events
     * @param  mixed  $listener
     * @param  int  $priority
     * @return void
     */
    public function listen($events, $listener, $priority = 0);

    /**
     * Déterminer si un événement donné a des auditeurs.
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners($eventName);

    /**
     * Enregistrez un événement et une charge utile à déclencher plus tards
     *
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function push($event, $payload = array());

    /**
     * Enregistrez un abonné à l'événement auprès du répartiteur.
     *
     * @param  object|string  $subscriber
     * @return void
     */
    public function subscribe($subscriber);

    /**
     * Lance un événement jusqu'à ce que la première réponse non nulle soit renvoyée.
     *
     * @param  string  $event
     * @param  array  $payload
     * @return mixed
     */
    public function until($event, $payload = array());

    /**
     * Flush un ensemble d'événements poussés.
     *
     * @param  string  $event
     * @return void
     */
    public function flush($event);

    /**
     * Envoyez un événement et appelez les auditeurs.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function dispatch($event, $payload = array(), $halt = false);

    /**
     * Obtenez l'événement qui se déclenche actuellement.
     *
     * @return string
     */
    public function dispatching();

    /**
     * Supprimer un ensemble d'auditeurs du répartiteur.
     *
     * @param  string  $event
     * @return void
     */
    public function forget($event);

    /**
     * Oubliez tous les auditeurs en file d'attente.
     *
     * @return void
     */
    public function forgetPushed();
}
