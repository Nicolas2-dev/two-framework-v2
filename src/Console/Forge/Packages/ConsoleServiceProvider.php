<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages;

use Two\Application\Providers\ServiceProvider;
use Two\Console\Forge\Packages\Commands\JobMakeCommand;
use Two\Console\Forge\Packages\Commands\EventMakeCommand;
use Two\Console\Forge\Packages\Commands\ModelMakeCommand;
use Two\Console\Forge\Packages\Commands\PolicyMakeCommand;
use Two\Console\Forge\Packages\Commands\SeederMakeCommand;
use Two\Console\Forge\Packages\Commands\ConsoleMakeCommand;
use Two\Console\Forge\Packages\Commands\PackageListCommand;
use Two\Console\Forge\Packages\Commands\PackageMakeCommand;
use Two\Console\Forge\Packages\Commands\PackageSeedCommand;
use Two\Console\Forge\Packages\Commands\RequestMakeCommand;
use Two\Console\Forge\Packages\Commands\ListenerMakeCommand;
use Two\Console\Forge\Packages\Commands\ProviderMakeCommand;
use Two\Console\Forge\Packages\Commands\MigrationMakeCommand;
use Two\Console\Forge\Packages\Commands\ControllerMakeCommand;
use Two\Console\Forge\Packages\Commands\MiddlewareMakeCommand;
use Two\Console\Forge\Packages\Commands\PackageMigrateCommand;
use Two\Console\Forge\Packages\Commands\PackageOptimizeCommand;
use Two\Console\Forge\Packages\Commands\NotificationMakeCommand;
use Two\Console\Forge\Packages\Commands\PackageMigrateResetCommand;
use Two\Console\Forge\Packages\Commands\PackageMigrateStatusCommand;
use Two\Console\Forge\Packages\Commands\PackageMigrateRefreshCommand;
use Two\Console\Forge\Packages\Commands\PackageMigrateRollbackCommand;


class ConsoleServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez les services d'application.
     */
    public function register()
    {
        $commands = array(
            'PackageList',
            'PackageMigrate',
            'PackageMigrateRefresh',
            'PackageMigrateReset',
            'PackageMigrateRollback',
            'PackageMigrateStatus',
            'PackageSeed',
            'PackageOptimize',

            //
            'PackageMake',
            'ConsoleMake',
            'ControllerMake',
            'EventMake',
            'JobMake',
            'ListenerMake',
            'MiddlewareMake',
            'ModelMake',
            'NotificationMake',
            'PolicyMake',
            'ProviderMake',
            'MigrationMake',
            'RequestMake',
            'SeederMake',
        );

        foreach ($commands as $command) {
            $method = 'register' .$command .'Command';

            call_user_func(array($this, $method));
        }
    }

    /**
     * Enregistrez la commande Package:list.
     */
    protected function registerPackageListCommand()
    {
        $this->app->singleton('command.package.list', function ($app)
        {
            return new PackageListCommand($app['packages']);
        });

        $this->commands('command.package.list');
    }

    /**
     * Enregistrez la commande Package:migrate.
     */
    protected function registerPackageMigrateCommand()
    {
        $this->app->singleton('command.package.migrate', function ($app)
        {
            return new PackageMigrateCommand($app['migrator'], $app['packages']);
        });

        $this->commands('command.package.migrate');
    }

    /**
     * Enregistrez la commande Package:migrate:refresh.
     */
    protected function registerPackageMigrateRefreshCommand()
    {
        $this->app->singleton('command.package.migrate.refresh', function ($app)
        {
            return new PackageMigrateRefreshCommand($app['packages']);
        });

        $this->commands('command.package.migrate.refresh');
    }

    /**
     * Enregistrez la commande Package:migrate:reset.
     */
    protected function registerPackageMigrateResetCommand()
    {
        $this->app->singleton('command.package.migrate.reset', function ($app)
        {
            return new PackageMigrateResetCommand($app['packages'], $app['files'], $app['migrator']);
        });

        $this->commands('command.package.migrate.reset');
    }

    /**
     * Enregistrez la commande Package:migrate:rollback.
     */
    protected function registerPackageMigrateRollbackCommand()
    {
        $this->app->singleton('command.package.migrate.rollback', function ($app)
        {
            return new PackageMigrateRollbackCommand($app['migrator'], $app['packages']);
        });

        $this->commands('command.package.migrate.rollback');
    }

    /**
     * Enregistrez la commande Package:migrate:reset.
     */
    protected function registerPackageMigrateStatusCommand()
    {
        $this->app->singleton('command.package.migrate.status', function ($app)
        {
            return new PackageMigrateStatusCommand($app['migrator'], $app['packages']);
        });

        $this->commands('command.package.migrate.status');
    }

    /**
     * Enregistrez la commande Package:seed.
     */
    protected function registerPackageSeedCommand()
    {
        $this->app->singleton('command.package.seed', function ($app)
        {
            return new PackageSeedCommand($app['packages']);
        });

        $this->commands('command.package.seed');
    }

    /**
     * Enregistrez la commande module:list.
     */
    protected function registerPackageOptimizeCommand()
    {
        $this->app->singleton('command.package.optimize', function ($app)
        {
            return new PackageOptimizeCommand($app['packages']);
        });

        $this->commands('command.package.optimize');
    }

    /**
     * Enregistrez la commande make:package.
     */
    private function registerPackageMakeCommand()
    {
        $this->app->bindShared('command.make.package', function ($app)
        {
            return new PackageMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package');
    }

    /**
     * Enregistrez la commande make:package:console.
     */
    private function registerConsoleMakeCommand()
    {
        $this->app->bindShared('command.make.package.console', function ($app)
        {
            return new ConsoleMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.console');
    }

    /**
     * Enregistrez la commande make:package:controller.
     */
    private function registerControllerMakeCommand()
    {
        $this->app->bindShared('command.make.package.controller', function ($app)
        {
            return new ControllerMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.controller');
    }

    /**
     * Enregistrez la commande make:package:event.
     */
    private function registerEventMakeCommand()
    {
        $this->app->bindShared('command.make.package.event', function ($app)
        {
            return new EventMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.event');
    }

    /**
     * Enregistrez la commande make:package:job.
     */
    private function registerJobMakeCommand()
    {
        $this->app->bindShared('command.make.package.job', function ($app)
        {
            return new JobMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.job');
    }

    /**
     * Enregistrez la commande make:package:listener.
     */
    private function registerListenerMakeCommand()
    {
        $this->app->bindShared('command.make.package.listener', function ($app)
        {
            return new ListenerMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.listener');
    }

    /**
     * Enregistrez la commande make:package:middleware.
     */
    private function registerMiddlewareMakeCommand()
    {
        $this->app->bindShared('command.make.package.middleware', function ($app)
        {
            return new MiddlewareMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.middleware');
    }

    /**
     * Enregistrez la commande make:package:model
     */
    private function registerModelMakeCommand()
    {
        $this->app->bindShared('command.make.package.model', function ($app)
        {
            return new ModelMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.model');
    }

    /**
     * Enregistrez la commande make:package:notification.
     */
    private function registerNotificationMakeCommand()
    {
        $this->app->bindShared('command.make.package.notification', function ($app)
        {
            return new NotificationMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.notification');
    }

    /**
     * Enregistrez la commande make:package:policy.
     */
    private function registerPolicyMakeCommand()
    {
        $this->app->bindShared('command.make.package.policy', function ($app)
        {
            return new PolicyMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.policy');
    }

    /**
     * Enregistrez la commande make:module:provider.
     */
    private function registerProviderMakeCommand()
    {
        $this->app->bindShared('command.make.package.provider', function ($app)
        {
            return new ProviderMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.provider');
    }

    /**
     * Enregistrez la commande make:package:migration.
     */
    private function registerMigrationMakeCommand()
    {
        $this->app->bindShared('command.make.package.migration', function ($app)
        {
            return new MigrationMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.migration');
    }

    /**
     * Enregistrez la commande make:module:provider.
     */
    private function registerRequestMakeCommand()
    {
        $this->app->bindShared('command.make.package.request', function ($app)
        {
            return new RequestMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.request');
    }

    /**
     * Enregistrez la commande make:package:seeder.
     */
    private function registerSeederMakeCommand()
    {
        $this->app->bindShared('command.make.package.seeder', function ($app)
        {
            return new SeederMakeCommand($app['files'], $app['packages']);
        });

        $this->commands('command.make.package.seeder');
    }
}
