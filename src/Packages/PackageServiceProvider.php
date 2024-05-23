<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Packages;

use Two\Packages\Repository;
use Two\Packages\PackageManager;
use Two\Application\Providers\ServiceProvider;


class PackageServiceProvider extends ServiceProvider
{
    /**
     * @var bool Indique si le chargement du Provider est différé.
     */
    protected $defer = false;

    /**
     * Démarrez le fournisseur de services.
     */
    public function boot()
    {
        $packages = $this->app['packages'];

        $packages->register();
    }

    /**
     * Enregistrez le fournisseur de services.
     */
    public function register()
    {
        $this->app->singleton('packages', function ($app)
        {
            $repository = new Repository($app['config'], $app['files']);

            return new PackageManager($app, $repository);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return string
     */
    public function provides()
    {
        return array('packages');
    }

}
