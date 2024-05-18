<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Events;


class NotificationSent
{
    /**
     * L'entité notifiable qui a reçu la notification.
     *
     * @var mixed
     */
    public $notifiable;

    /**
     * L'instance de notification.
     *
     * @var \Two\Notifications\Notification
     */
    public $notification;

    /**
     * Le nom de la chaîne.
     *
     * @var string
     */
    public $channel;

    /**
     * La réponse de la chaîne.
     *
     * @var mixed
     */
    public $response;

    /**
     * Créez une nouvelle instance d'événement.
     *
     * @param  mixed  $notifiable
     * @param  \Two\Notifications\Notification  $notification
     * @param  string  $channel
     * @param  mixed  $response
     * @return void
     */
    public function __construct($notifiable, $notification, $channel, $response = null)
    {
        $this->channel = $channel;
        $this->response = $response;
        $this->notifiable = $notifiable;
        $this->notification = $notification;
    }
}
