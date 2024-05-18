<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Events;

use Two\Bus\Traits\QueueableTrait;
use Two\Queue\Traits\SerializesModelsTrait;
use Two\Broadcasting\Contracts\ShouldBroadcastInterface;


class BroadcastNotificationCreated implements ShouldBroadcastInterface
{
    use QueueableTrait, SerializesModelsTrait;

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
     * Les données de notification.
     *
     * @var array
     */
    public $data = array();

    /**
     * Créez une nouvelle instance d'événement.
     *
     * @param  mixed  $notifiable
     * @param  \Two\Notifications\Notification  $notification
     * @param  array  $data
     * @return void
     */
    public function __construct($notifiable, $notification, $data)
    {
        $this->data = $data;
        $this->notifiable = $notifiable;
        $this->notification = $notification;
    }

    /**
     * Obtenez les chaînes sur lesquelles l'événement doit être diffusé.
     *
     * @return array
     */
    public function broadcastOn()
    {
        $channels = $this->notification->broadcastOn();

        if (! empty($channels)) {
            return $channels;
        }

        $channel = 'private-' .$this->channelName();

        return array($channel);
    }

    /**
     * Obtenez les données qui doivent être envoyées avec l'événement diffusé.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return array_merge($this->data, array(
            'id'   => $this->notification->id,
            'type' => get_class($this->notification),
        ));
    }

    /**
     * Obtenez le nom de la chaîne de diffusion de l'événement.
     *
     * @return string
     */
    protected function channelName()
    {
        if (method_exists($this->notifiable, 'receivesBroadcastNotificationsOn')) {
            return $this->notifiable->receivesBroadcastNotificationsOn($this->notification);
        }

        $className = str_replace('\\', '.', get_class($this->notifiable));

        return $className .'.' .$this->notifiable->getKey();
    }
}
