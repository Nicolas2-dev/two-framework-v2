<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Events;

use Two\Application\Providers\ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    
    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app['events'] = $this->app->share(function($app)
        {
            return with(new Dispatcher($app))->setQueueResolver(function () use ($app)
            {
                return $app['queue'];
            });
        });
    }

}
