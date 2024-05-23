<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache;


use Two\Cache\Memcached\MemcachedConnector;

use Two\Application\Providers\ServiceProvider;


class CacheServiceProvider extends ServiceProvider
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
        $this->app->singleton('cache', function($app)
        {
            return new CacheManager($app);
        });

        $this->app->singleton('cache.store', function($app)
        {
            return $app['cache']->driver();
        });

        $this->app->singleton('memcached.connector', function()
        {
            return new MemcachedConnector;
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
            'cache', 'cache.store', 'memcached.connector'
        );
    }

}
