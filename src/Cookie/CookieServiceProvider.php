<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cookie;

use Two\Cookie\CookieJar;

use Two\Application\Providers\ServiceProvider;


class CookieServiceProvider extends ServiceProvider
{
    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cookie', function($app)
        {
            $config = $app['config']['session'];

            return with(new CookieJar())->setDefaultPathAndDomain($config['path'], $config['domain']);
        });
    }
}
