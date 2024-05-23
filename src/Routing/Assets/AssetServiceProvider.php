<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing\Assets;

use Two\Routing\Assets\AssetManager;
use Two\Routing\Assets\AssetDispatcher;

use Two\Application\Providers\ServiceProvider;


class AssetServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerAssetManager();

        $this->registerAssetDispatcher();
    }

    /**
     * Enregistrez l'instance Asset Manager.
     *
     * @return void
     */
    protected function registerAssetManager()
    {
        $this->app->singleton('assets', function ($app)
        {
            return new AssetManager($app['view']);
        });
    }

    /**
     * Enregistrez l’instance Assets Dispatcher.
     *
     * @return void
     */
    protected function registerAssetDispatcher()
    {
        $this->app->singleton('assets.dispatcher', function ($app)
        {
            return new AssetDispatcher($app);
        });
    }
}
