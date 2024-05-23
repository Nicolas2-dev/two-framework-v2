<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting;


use Two\Broadcasting\BroadcastManager;

use Two\Application\Providers\ServiceProvider;


class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Indique si le chargement du fournisseur est différé.
     *
     * @var bool
     */
    protected $defer = false;


    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Two\Broadcasting\BroadcastManager', function ($app)
        {
            return new BroadcastManager($app);
        });

        $this->app->singleton('Two\Broadcasting\Contracts\BroadcasterInterface', function ($app)
        {
            return $app->make('Two\Broadcasting\BroadcastManager')->connection();
        });

        $this->app->alias(
            'Two\Broadcasting\BroadcastManager', 'Two\Broadcasting\Contracts\FactoryInterface'
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
            'Two\Broadcasting\BroadcastManager',
            'Two\Broadcasting\Contracts\FactoryInterface',
            'Two\Broadcasting\Contracts\BroadcasterInterface',
        );
    }
}
