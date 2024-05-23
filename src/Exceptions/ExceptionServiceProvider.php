<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions;


use Two\Exceptions\ExceptionHandler;

use Two\Application\Providers\ServiceProvider;


class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app['exception'] = $this->app->share(function ($app)
        {
            return new ExceptionHandler($app);
        });
    }
}