<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database;

use Two\Database\Seeder;
use \Two\Application\Providers\ServiceProvider;
use Two\Console\Forge\Database\Commands\Seeds\SeedCommand;
use Two\Console\Forge\Database\Commands\Seeds\SeederMakeCommand;


class SeedingServiceProvider extends ServiceProvider
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
        $this->registerSeedCommand();

        $this->registerMakeCommand();

        $this->app->singleton('seeder', function($app)
        {
            return new Seeder();
        });

        $this->commands('command.seed', 'command.seeder.make');
    }

    /**
     * Enregistrez la commande de la console seed.
     *
     * @return void
     */
    protected function registerSeedCommand()
    {
        $this->app->singleton('command.seed', function($app)
        {
            return new SeedCommand($app['db']);
        });
    }

    /**
     * Enregistrez la commande du générateur de semoir.
     *
     * @return void
     */
    protected function registerMakeCommand()
    {
        $this->app->singleton('command.seeder.make', function ($app)
        {
            return new SeederMakeCommand($app['files'], $app['composer']);
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
            'seeder', 'command.seed', 'command.seeder.make'
        );
    }

}
