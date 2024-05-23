<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Localization;

use \Two\Application\Providers\ServiceProvider;
use Two\Console\Forge\Localization\Commands\LanguagesUpdateCommand;


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
        $this->app->singleton('command.languages.update', function ($app)
        {
            return new LanguagesUpdateCommand($app['language'], $app['files']);
        });

        $this->commands('command.languages.update');
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array('command.languages.update');
    }

}
