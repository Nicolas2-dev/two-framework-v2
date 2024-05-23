<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Notifications;

use Two\Application\Providers\ServiceProvider;


use Two\Notifications\ChannelManager;
use Two\Bus\Contracts\DispatcherInterface as BusDispatcher;


class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Indique si le chargement du Provider est différé.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Enregistrez le fournisseur de services du plugin Notifications.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('notifications', function ($app)
        {
            $bus = $app->make(BusDispatcher::class);

            return new ChannelManager($app, $app['events'], $bus);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array('notifications');
    }
}
