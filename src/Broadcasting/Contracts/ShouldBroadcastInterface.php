<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting\Contracts;


interface ShouldBroadcastInterface
{
    /**
     * Obtenez les chaînes sur lesquelles l'événement doit être diffusé.
     *
     * @return array
     */
    public function broadcastOn();
}
