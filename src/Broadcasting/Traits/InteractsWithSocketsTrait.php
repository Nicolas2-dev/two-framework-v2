<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting;

use Two\Support\Facades\Broadcast;


trait InteractsWithSocketsTrait
{
    /**
     * L'ID de socket de l'utilisateur qui a déclenché l'événement.
     *
     * @var string|null
     */
    public $socket;


    /**
     * Exclure l'utilisateur actuel de la réception de la diffusion.
     *
     * @return $this
     */
    public function dontBroadcastToCurrentUser()
    {
        $this->socket = Broadcast::socket();

        return $this;
    }

    /**
     * Diffusez l’événement à tout le monde.
     *
     * @return $this
     */
    public function broadcastToEveryone()
    {
        $this->socket = null;

        return $this;
    }
}
