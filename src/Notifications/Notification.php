<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications;

use Two\Queue\Traits\SerializesModelsTrait;


class Notification
{
    use SerializesModelsTrait;

    /**
     * L'identifiant unique de la notification.
     *
     * @var string
     */
    public $id;


    /**
     * Obtenez les chaînes sur lesquelles l'événement doit être diffusé.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return array();
    }
}
