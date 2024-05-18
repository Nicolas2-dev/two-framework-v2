<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications;

use Two\Support\Facades\Config;
use Two\Support\Facades\Notification as Notifier;
use Two\Support\Str;


trait NotifiableTrait
{
    /**
     * Recevez les notifications de l'entité.
     */
    public function notifications()
    {
        return $this->morphMany('Two\Notifications\Models\Notification', 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Recevez les notifications de lecture de l'entité.
     */
    public function readNotifications()
    {
        return $this->notifications()->whereNotNull('read_at');
    }

    /**
     * Recevez les notifications non lues de l'entité.
     */
    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    /**
     * Envoyez la notification donnée.
     *
     * @param  mixed  $instance
     * @return void
     */
    public function notify($instance)
    {
        return Notifier::send(array($this), $instance);
    }

    /**
     * Envoyez immédiatement la notification donnée.
     *
     * @param  mixed  $instance
     * @param  array|null  $channels
     * @return void
     */
    public function notifyNow($instance, array $channels = null)
    {
        return Notifier::sendNow($this, $instance, $channels);
    }

    /**
     * Obtenez les informations de routage des notifications pour le pilote donné.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function routeNotificationFor($driver)
    {
        $method = 'routeNotificationFor'. Str::studly($driver);

        if (method_exists($this, $method)) {
            return call_user_func(array($this, $method));
        }

        // Aucune méthode personnalisée pour acheminer les notifications.
        else if ($driver == 'database') {
            return $this->notifications();
        }

        // Enfin, nous n'accepterons que le pilote de messagerie.
        else if ($driver != 'mail') {
            return null;
        }

        // Si le champ email ressemble à : admin@Twoframework.local
        if (preg_match('/^\w+@\w+\.local$/s', $this->email) === 1) {
            return Config::get('mail.from.address');
        }

        return $this->email;
    }

}
