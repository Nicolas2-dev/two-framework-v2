<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications;

use Two\Bus\Traits\QueueableTrait;
use Two\Queue\contracts\ShouldQueueInterface;
use Two\Queue\Traits\SerializesModelsTrait;
use Two\Notifications\ChannelManager;


class SendQueuedNotifications implements ShouldQueueInterface
{
    use QueueableTrait, SerializesModelsTrait;

    /**
     * Les entités notifiables qui doivent recevoir la notification.
     *
     * @var \Two\Collection\Collection
     */
    protected $notifiables;

    /**
     * La notification à envoyer.
     *
     * @var \Two\Notifications\Notification
     */
    protected $notification;

    /**
     * Tous les canaux pour envoyer la notification également.
     *
     * @var array
     */
    protected $channels;


    /**
     * Créez une nouvelle instance de travail.
     *
     * @param  \Two\Collection\Collection  $notifiables
     * @param  \Two\Notifications\Notification  $notification
     * @param  array  $channels
     * @return void
     */
    public function __construct($notifiables, $notification, array $channels = null)
    {
        $this->channels = $channels;
        $this->notifiables = $notifiables;
        $this->notification = $notification;
    }

    /**
     * Envoyez les notifications.
     *
     * @param  \Two\Notifications\ChannelManager  $manager
     * @return void
     */
    public function handle(ChannelManager $manager)
    {
        $manager->sendNow($this->notifiables, $this->notification, $this->channels);
    }
}
