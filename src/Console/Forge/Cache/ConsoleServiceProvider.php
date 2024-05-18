<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Cache;

use Two\TwoApplication\Providers\ServiceProvider;
use Two\Console\Forge\Cache\Commands\ClearCommand;
use Two\Console\Forge\Cache\Commands\ForgetCommand;
use Two\Console\Forge\Cache\Commands\CacheTableCommand;


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
        $this->app->singleton('command.cache.clear', function ($app)
        {
            return new ClearCommand($app['cache'], $app['files']);
        });

        $this->app->singleton('command.cache.forget', function ($app)
        {
            return new ForgetCommand($app['cache']);
        });

        $this->app->singleton('command.cache.table', function ($app)
        {
            return new CacheTableCommand($app['files']);
        });

        $this->commands('command.cache.clear', 'command.cache.forget', 'command.cache.table');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'command.cache.clear', 'command.cache.forget', 'command.cache.table'
        );
    }

}
