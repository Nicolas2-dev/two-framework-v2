<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Localization;

use Two\TwoApplication\Providers\ServiceProvider;
use Two\Console\Forge\Localization\Commands\LanguagesUpdateCommand;


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
        $this->app->singleton('command.languages.update', function ($app)
        {
            return new LanguagesUpdateCommand($app['language'], $app['files']);
        });

        $this->commands('command.languages.update');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('command.languages.update');
    }

}