<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Session;

use Two\Application\Providers\ServiceProvider;


class SessionServiceProvider extends ServiceProvider
{
    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->setupDefaultDriver();

        $this->registerSessionManager();

        $this->registerSessionDriver();

        //
        $this->app->singleton('Two\Application\Middleware\Sessions\StartSession');
    }

    /**
     * Configurez le pilote de session par défaut pour l'application.
     *
     * @return void
     */
    protected function setupDefaultDriver()
    {
        if ($this->app->runningInConsole()) {
            $this->app['config']['session.driver'] = 'array';
        }
    }

    /**
     * Enregistrez l'instance du gestionnaire de sessions.
     *
     * @return void
     */
    protected function registerSessionManager()
    {
        $this->app->singleton('session', function($app)
        {
            return new SessionManager($app);
        });
    }

    /**
     * Enregistrez l'instance du pilote de session.
     *
     * @return void
     */
    protected function registerSessionDriver()
    {
        $this->app->bindShared('session.store', function($app)
        {
            $manager = $app['session'];

            return $manager->driver();
        });
    }

}
