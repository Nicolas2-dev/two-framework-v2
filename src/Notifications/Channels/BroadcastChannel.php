<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Channels;

use RuntimeException;

use Two\Events\Dispatcher;
use Two\Notifications\Messages\BroadcastMessage;
use Two\Notifications\Events\BroadcastNotificationCreated;
use Two\Notifications\Notification;


class BroadcastChannel
{
    /**
     * Le répartiteur d'événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * Créez un nouveau canal de base de données.
     *
     * @param  \Two\Events\Dispatcher  $events
     * @return void
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Envoyez la notification donnée.
     *
     * @param  mixed  $notifiable
     * @param  \Two\Notifications\Notification  $notification
     * @return array|null
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $this->getData($notifiable, $notification);

        $event = new BroadcastNotificationCreated(
            $notifiable, $notification, is_array($message) ? $message : $message->data
        );

        if ($message instanceof BroadcastMessage) {
            $event->onConnection($message->connection)
                  ->onQueue($message->queue);
        }

        return $this->events->dispatch($event);
    }

    /**
     * Obtenez les données pour la notification.
     *
     * @param  mixed  $notifiable
     * @param  \Two\Notifications\Notification  $notification
     * @return mixed
     *
     * @throws \RuntimeException
     */
    protected function getData($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toBroadcast')) {
            return $notification->toBroadcast($notifiable);
        }

        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        throw new RuntimeException(
            'Notification is missing toArray method.'
        );
    }
}
