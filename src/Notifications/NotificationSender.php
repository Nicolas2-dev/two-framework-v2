<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications;

use Two\Database\ORM\Model;
use Two\collection\Collection;
use Two\Queue\Contracts\ShouldQueueInterface;
use Two\Notifications\ChannelManager;
use Two\Bus\Dispatcher as BusDispatcher;
use Two\Events\Dispatcher as EventDispatcher;
use Two\Notifications\Events\NotificationSent;
use Two\Notifications\SendQueuedNotifications;
use Two\Notifications\Events\NotificationSending;
use Two\Database\ORM\Collection as ModelCollection;

use Ramsey\Uuid\Uuid;


class NotificationSender
{
    /**
     * L'instance du gestionnaire de notifications.
     *
     * @var \Two\Notifications\ChannelManager
     */
    protected $manager;

    /**
     * Instance du répartiteur d’événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * Instance du répartiteur de bus de commande.
     *
     * @var \Two\Bus\Dispatcher
     */
    protected $bus;


    /**
     * Créez une nouvelle instance d'expéditeur de notification.
     *
     * @param  \Two\Events\Dispatcher  $events
     * @param  \Two\Bus\Dispatcher  $bus
     * @return void
     */
    public function __construct(ChannelManager $manager, EventDispatcher $events, BusDispatcher $bus)
    {
        $this->manager = $manager;

        $this->events = $events;

        $this->bus = $bus;
    }

    /**
     * Envoyez la notification donnée aux entités notifiables indiquées.
     *
     * @param  \Two\Support\Collection|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        if (! $notification instanceof ShouldQueueInterface) {
            return $this->sendNow($notifiables, $notification);
        }

        foreach ($notifiables as $notifiable) {
            $notificationId = Uuid::uuid4()->toString();

            foreach ($notification->via($notifiable) as $channel) {
                $this->queueToNotifiable($notifiable, $notificationId, clone $notification, $channel);
            }
        }
    }

    /**
     * Envoyez la notification donnée aux entités notifiables indiquées.
     *
     * @param  \Two\Support\Collection|array|mixed  $notifiables
     * @param  mixed  $notification
     * @param  array|null  $channels
     * @return void
     */
    public function sendNow($notifiables, $notification, array $channels = null)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        $original = clone $notification;

        foreach ($notifiables as $notifiable) {
            if (empty($viaChannels = $channels ?: $notification->via($notifiable))) {
                continue;
            }

            $notificationId = Uuid::uuid4()->toString();

            foreach ((array) $viaChannels as $channel) {
                $this->sendToNotifiable($notifiable, $notificationId, clone $original, $channel);
            }
        }
    }

    /**
     * Envoyez la notification donnée au notifiable donné via un canal.
     *
     * @param  mixed  $notifiable
     * @param  string  $id
     * @param  mixed  $notification
     * @param  string  $channel
     * @return void
     */
    protected function sendToNotifiable($notifiable, $id, $notification, $channel)
    {
        if (is_null($notification->id)) {
            $notification->id = $id;
        }

        if ($this->shouldSendNotification($notifiable, $notification, $channel)) {
            $response = $this->manager->channel($channel)->send($notifiable, $notification);

            $this->events->dispatch(
                new NotificationSent($notifiable, $notification, $channel, $response)
            );
        }
    }

    /**
     * Détermine si la notification peut être envoyée.
     *
     * @param  mixed  $notifiable
     * @param  mixed  $notification
     * @param  string  $channel
     * @return bool
     */
    protected function shouldSendNotification($notifiable, $notification, $channel)
    {
        $result = $this->events->until(
            new NotificationSending($notifiable, $notification, $channel)
        );

        return ($result !== false);
    }

    /**
     * Mettez en file d'attente la notification donnée vers le notifiable donné via un canal.
     *
     * @param  mixed  $notifiable
     * @param  string  $id
     * @param  mixed  $notification
     * @param  string  $channel
     * @return void
     */
    protected function queueToNotifiable($notifiable, $id, $notification, $channel)
    {
        $notification->id = $id;

        $job = with(new SendQueuedNotifications($notifiable, $notification, array($channel)))
            ->onConnection($notification->connection)
            ->onQueue($notification->queue)
            ->delay($notification->delay);

        $this->bus->dispatch($job);
    }

    /**
     * Formatez les notifiables dans une collection/un tableau si nécessaire.
     *
     * @param  mixed  $notifiables
     * @return ModelCollection|array
     */
    protected function formatNotifiables($notifiables)
    {
        if ((! $notifiables instanceof Collection) && ! is_array($notifiables)) {
            $items = array($notifiables);

            if ($notifiables instanceof Model) {
                return new ModelCollection($items);
            }

            return $items;
        }

        return $notifiables;
    }
}
