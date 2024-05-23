<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling;

use Two\Console\Scheduling\Schedule;
use Two\Console\Scheduling\ScheduleRunCommand;
use Two\Console\Scheduling\ScheduleFinishCommand;
use Two\Application\Providers\ServiceProvider;


class ScheduleServiceProvider extends ServiceProvider
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
        $this->app->singleton('schedule', function ($app)
        {
            return new Schedule($app);
        });

        $this->registerScheduleRunCommand();
        $this->registerScheduleFinishCommand();
    }

    /**
     * Enregistrez la commande d'exécution planifiée.
     *
     * @return void
     */
    protected function registerScheduleRunCommand()
    {
        $this->app->singleton('command.schedule.run', function ($app)
        {
            return new ScheduleRunCommand($app['schedule']);
        });

        $this->commands('command.schedule.run');
    }

    /**
     * Enregistrez la commande d'exécution planifiée.
     *
     * @return void
     */
    protected function registerScheduleFinishCommand()
    {
        $this->app->singleton('command.schedule.finish', function ($app)
        {
            return new ScheduleFinishCommand($app['schedule']);
        });

        $this->commands('command.schedule.finish');
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'command.schedule.run', 'command.schedule.finish'
        );
    }
}
