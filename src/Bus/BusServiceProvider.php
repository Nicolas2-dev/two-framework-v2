<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus;

use Two\Application\Providers\ServiceProvider;


class BusServiceProvider extends ServiceProvider
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
        $this->app->singleton('Two\Bus\Dispatcher', function ($app)
        {
            return new Dispatcher($app, function ($connection = null) use ($app)
            {
                return $app->make('queue')->connection($connection);
            });
        });

        $this->app->alias(
            'Two\Bus\Dispatcher', 'Two\Bus\Contracts\DispatcherInterface'
        );

        $this->app->alias(
            'Two\Bus\Dispatcher', 'Two\Bus\Contracts\QueueingDispatcherInterface'
        );
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'Two\Bus\Dispatcher',
            'Two\Bus\Contracts\DispatcherInterface',
            'Two\Bus\Contracts\QueueingDispatcherInterface',
        ];
    }
}
