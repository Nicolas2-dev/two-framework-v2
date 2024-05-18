<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Notifications;

use Two\TwoApplication\Providers\ServiceProvider;
use Two\Console\Forge\Notifications\Commands\NotificationMakeCommand;
use Two\Console\Forge\Notifications\Commands\NotificationTableCommand;


class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.notification.table', function ($app)
        {
            return new NotificationTableCommand($app['files']);
        });

        $this->app->singleton('command.notification.make', function ($app)
        {
            return new NotificationMakeCommand($app['files']);
        });

        $this->commands('command.notification.table', 'command.notification.make');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'command.notification.table', 'command.notification.make'
        );
    }

}
