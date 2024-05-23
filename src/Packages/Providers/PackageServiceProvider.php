<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Packages\Providers;


use Two\Application\Providers\ServiceProvider;


class PackageServiceProvider extends ServiceProvider
{
    /**
     * Les noms de classe du fournisseur.
     *
     * @var array
     */
    protected $providers = array();


    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }
    }

    protected function bootstrapFrom($path)
    {
        $app = $this->app;

        return require $path;
    }
}
