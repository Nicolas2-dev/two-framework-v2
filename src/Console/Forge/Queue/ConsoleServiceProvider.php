<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue;

use Two\Application\Providers\ServiceProvider;
use Two\Console\Forge\Queue\Commands\RetryCommand;
use Two\Console\Forge\Queue\Commands\TableCommand;
use Two\Console\Forge\Queue\Commands\ListFailedCommand;
use Two\Console\Forge\Queue\Commands\FailedTableCommand;
use Two\Console\Forge\Queue\Commands\FlushFailedCommand;
use Two\Console\Forge\Queue\Commands\ForgetFailedCommand;


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
        $this->app->singleton('command.queue.table', function ($app) {
            return new TableCommand($app['files']);
        });

        $this->app->singleton('command.queue.failed', function()
        {
            return new ListFailedCommand;
        });

        $this->app->singleton('command.queue.retry', function()
        {
            return new RetryCommand;
        });

        $this->app->singleton('command.queue.forget', function()
        {
            return new ForgetFailedCommand;
        });

        $this->app->singleton('command.queue.flush', function()
        {
            return new FlushFailedCommand;
        });

        $this->app->singleton('command.queue.failed-table', function($app)
        {
            return new FailedTableCommand($app['files']);
        });

        $this->commands(
            'command.queue.table', 'command.queue.failed', 'command.queue.retry',
            'command.queue.forget', 'command.queue.flush', 'command.queue.failed-table'
        );
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'command.queue.table', 'command.queue.failed', 'command.queue.retry',
            'command.queue.forget', 'command.queue.flush', 'command.queue.failed-table',
        );
    }

}
