<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications;

use InvalidArgumentException;

use Two\Application\Manager;
use Two\Application\Two;
use Two\Bus\Dispatcher as BusDispatcher;
use Two\Notifications\NotificationSender;
use Two\Notifications\Channels\MailChannel;
use Two\Events\Dispatcher as EventDispatcher;
use Two\Notifications\Channels\DatabaseChannel;
use Two\Notifications\Channels\BroadcastChannel;
use Two\Notifications\contracts\DispatcherInterface;


class ChannelManager extends Manager implements DispatcherInterface
{
    /**
     * Instance de l’expéditeur des notifications.
     *
     * @var \Two\Notifications\NotificationSender
     */
    protected $sender;

    /**
     * Les canaux par défaut utilisés pour transmettre les messages.
     *
     * @var array
     */
    protected $defaultChannel = 'mail';


    /**
     * Créez une nouvelle instance de gestionnaire.
     *
     * @param  \Two\Application\Two  $app
     * @param  \Two\Events\Dispatcher  $events
     * @param  \Two\Bus\Dispatcher  $bus
     * @return void
     */
    public function __construct(Two $app, EventDispatcher $events, BusDispatcher $bus)
    {
        $this->app = $app;

        //
        $this->sender = new NotificationSender($this, $events, $bus);
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
        $this->sender->send($notifiables, $notification);
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
        $this->sender->sendNow($notifiables, $notification, $channels);
    }

    /**
     * Obtenez une instance de canal.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function channel($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Créez une instance du pilote de base de données.
     *
     * @return \Two\Notifications\Channels\DatabaseChannel
     */
    protected function createDatabaseDriver()
    {
        return $this->app->make(DatabaseChannel::class);
    }

    /**
     * Créez une instance du pilote de diffusion.
     *
     * @return \Two\Notifications\Channels\BroadcastChannel
     */
    protected function createBroadcastDriver()
    {
        return $this->app->make(BroadcastChannel::class);
    }

    /**
     * Créez une instance du pilote de messagerie.
     *
     * @return \Two\Notifications\Channels\MailChannel
     */
    protected function createMailDriver()
    {
        return $this->app->make(MailChannel::class);
    }

    /**
     * Créez une nouvelle instance de pilote.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        try {
            return parent::createDriver($driver);
        }
        catch (InvalidArgumentException $e) {
            if (! class_exists($driver)) {
                throw $e;
            }

            return $this->app->make($driver);
        }
    }

    /**
     * Obtenez le nom du pilote de canal par défaut.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->defaultChannel;
    }

    /**
     * Obtenez le nom du pilote de canal par défaut.
     *
     * @return string
     */
    public function deliversVia()
    {
        return $this->defaultChannel;
    }

    /**
     * Définissez le nom du pilote de canal par défaut.
     *
     * @param  string  $channel
     * @return void
     */
    public function deliverVia($channel)
    {
        $this->defaultChannel = $channel;
    }
}
