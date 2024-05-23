<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console;

use Two\Console\Forge;
use Two\Application\Composer;
use Two\Application\Providers\ServiceProvider;


class TwoConsoleServiceProvider extends ServiceProvider
{
    /**
     * Indiquez si le chargement du fournisseur est différé.
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
        $this->app->singleton('composer', function($app)
        {
            return new Composer($app['files'], $app['path.base']);
        });

        $this->app->singleton('forge', function($app)
        {
           return new Forge($app);
        });

        // Enregistrez les fournisseurs de services supplémentaires.
        $this->app->register('Two\Console\Scheduling\ScheduleServiceProvider');
        $this->app->register('Two\Console\Forge\Queue\ConsoleServiceProvider');
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array('composer', 'forge');
    }

}
