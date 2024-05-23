<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Hashing;

use Two\Hashing\BcryptHasher;

use Two\Application\Providers\ServiceProvider;


class HashServiceProvider extends ServiceProvider
{
    
    /**
     * Indique si le chargement du Provider est différé.
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
        $this->app->singleton('hash', function()
        {
            return new BcryptHasher();
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array('hash');
    }

}
