<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting;

use Two\Events\Dispatcher;


class PendingBroadcast
{
    /**
     * L’implémentation du répartiteur d’événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * L'instance d'événement.
     *
     * @var mixed
     */
    protected $event;


    /**
     * Créez une nouvelle instance de diffusion en attente.
     *
     * @param  \Two\Events\Dispatcher  $events
     * @param  mixed  $event
     * @return void
     */
    public function __construct(Dispatcher $events, $event)
    {
        $this->event  = $event;
        $this->events = $events;
    }

    /**
     * Gérez la destruction de l'objet.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->events->dispatch($this->event);
    }

    /**
     * Diffusez l'événement à tout le monde sauf à l'utilisateur actuel.
     *
     * @return $this
     */
    public function toOthers()
    {
        if (method_exists($this->event, 'dontBroadcastToCurrentUser')) {
            $this->event->dontBroadcastToCurrentUser();
        }

        return $this;
    }

}
