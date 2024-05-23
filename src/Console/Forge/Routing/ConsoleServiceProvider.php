<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Routing;

use Two\Application\Providers\ServiceProvider;
use Two\Console\Forge\Routing\Commands\RouteListCommand;
use Two\Console\Forge\Routing\Commands\ControllerMakeCommand;
use Two\Console\Forge\Routing\Commands\MiddlewareMakeCommand;


class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Indique si le chargement du fournisseur est différé.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.controller.make', function($app)
        {
            return new ControllerMakeCommand($app['files']);
        });

        $this->app->singleton('command.middleware.make', function($app)
        {
            return new MiddlewareMakeCommand($app['files']);
        });

        $this->app->singleton('command.route.list', function ($app)
        {
            return new RouteListCommand($app['router']);
        });

        $this->commands('command.controller.make', 'command.middleware.make', 'command.route.list');
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'command.controller.make', 'command.middleware.make', 'command.route.list'
        );
    }

}
