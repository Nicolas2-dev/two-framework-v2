<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database;

use Two\Application\Providers\ServiceProvider;
use Two\Console\Forge\Database\Migrations\Migrator;
use Two\Console\Forge\Database\Migrations\MigrationCreator;
use Two\Console\Forge\Database\Commands\Migrations\ResetCommand;
use Two\Console\Forge\Database\Commands\Migrations\StatusCommand;
use Two\Console\Forge\Database\Commands\Migrations\InstallCommand;
use Two\Console\Forge\Database\Commands\Migrations\MigrateCommand;
use Two\Console\Forge\Database\Commands\Migrations\RefreshCommand;
use Two\Console\Forge\Database\Commands\Migrations\RollbackCommand;
use Two\Console\Forge\Database\Migrations\DatabaseMigrationRepository;
use Two\Console\Forge\Database\Commands\Migrations\MakeMigrationCommand;



class MigrationServiceProvider extends ServiceProvider
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
        $this->registerRepository();

        $this->registerMigrator();

        $this->registerCommands();
    }

    /**
     * Enregistrez le service de référentiel de migration.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->singleton('migration.repository', function($app)
        {
            $table = $app['config']['database.migrations'];

            return new DatabaseMigrationRepository($app['db'], $table);
        });
    }

    /**
     * Enregistrez le service de migration.
     *
     * @return void
     */
    protected function registerMigrator()
    {
        $this->app->singleton('migrator', function($app)
        {
            $repository = $app['migration.repository'];

            return new Migrator($repository, $app['db'], $app['files']);
        });
    }

    /**
     * Enregistrez toutes les commandes de migration.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $commands = array('Migrate', 'Rollback', 'Reset', 'Refresh', 'Install', 'Make', 'Status');

        foreach ($commands as $command) {
            $this->{'register' .$command .'Command'}();
        }

        $this->commands(
            'command.migrate', 'command.migrate.make',
            'command.migrate.install', 'command.migrate.rollback',
            'command.migrate.reset', 'command.migrate.refresh',
            'command.migrate.status'
        );
    }

    /**
     * Enregistrez la commande de migration « migrate ».
     *
     * @return void
     */
    protected function registerMigrateCommand()
    {
        $this->app->singleton('command.migrate', function($app)
        {
            $packagePath = $app['path.base'] .DS .'vendor';

            return new MigrateCommand($app['migrator'], $packagePath);
        });
    }

    /**
     * Enregistrez la commande de migration "rollback".
     *
     * @return void
     */
    protected function registerRollbackCommand()
    {
        $this->app->singleton('command.migrate.rollback', function($app)
        {
            return new RollbackCommand($app['migrator']);
        });
    }

    /**
     * Enregistrez la commande de migration "reset".
     *
     * @return void
     */
    protected function registerResetCommand()
    {
        $this->app->singleton('command.migrate.reset', function($app)
        {
            return new ResetCommand($app['migrator']);
        });
    }

    /**
     * Enregistrez la commande de migration « actualiser ».
     *
     * @return void
     */
    protected function registerRefreshCommand()
    {
        $this->app->singleton('command.migrate.refresh', function($app)
        {
            return new RefreshCommand;
        });
    }

    /**
     * Enregistrez la commande de migration « statut ».
     *
     * @return void
     */
    protected function registerStatusCommand()
    {
        $this->app->singleton('command.migrate.status', function ($app)
        {
            return new StatusCommand($app['migrator']);
        });
    }

    /**
     * Enregistrez la commande de migration « installer ».
     *
     * @return void
     */
    protected function registerInstallCommand()
    {
        $this->app->singleton('command.migrate.install', function($app)
        {
            return new InstallCommand($app['migration.repository']);
        });
    }

    /**
     * Enregistrez la commande de migration « installer ».
     *
     * @return void
     */
    protected function registerMakeCommand()
    {
        $this->app->singleton('migration.creator', function($app)
        {
            return new MigrationCreator($app['files']);
        });

        $this->app->singleton('command.migrate.make', function($app)
        {
            $creator = $app['migration.creator'];

            $packagePath = $app['path.base'] .DS .'vendor';

            return new MakeMigrationCommand($creator, $packagePath);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'migrator', 'migration.repository', 'command.migrate',
            'command.migrate.rollback', 'command.migrate.reset',
            'command.migrate.refresh', 'command.migrate.install',
            'migration.creator', 'command.migrate.make',
            'command.migrate.status',
        );
    }

}
