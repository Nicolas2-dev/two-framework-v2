<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications\Channels;

use RuntimeException;

use Two\Notifications\Notification;


class DatabaseChannel
{
    /**
     * Envoyez la notification donnée.
     *
     * @param  mixed  $notifiable
     * @param  \Two\Notifications\Notification  $notification
     * @return \Two\Database\ORM\Model
     */
    public function send($notifiable, Notification $notification)
    {
        return $notifiable->routeNotificationFor('database')->create(array(
            'uuid'        => $notification->id,
            'type'        => get_class($notification),
            'data'        => $this->getData($notifiable, $notification),
            'read_at'    => null,
        ));
    }

    /**
     * Obtenez les données pour la notification.
     *
     * @param  mixed  $notifiable
     * @param  \Two\Notifications\Notification  $notification
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function getData($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($notifiable);

            return is_array($data) ? $data : $data->data;
        } else if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        throw new RuntimeException('Notification is missing toDatabase / toArray method.');
    }
}
