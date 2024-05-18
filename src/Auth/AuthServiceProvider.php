<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth;

use Two\Auth\Access\Gate;
use Two\TwoApplication\Providers\ServiceProvider;


class AuthServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerAuthenticator();

        $this->registerUserResolver();

        $this->registerAccessGate();

        $this->registerRequestRebindHandler();
    }

    /**
     * Enregistrez les services d'authentification.
     *
     * @return void
     */
    protected function registerAuthenticator()
    {
        $this->app->singleton('auth', function($app)
        {
            // Une fois le service d'authentification effectivement demandé par le développeur
            // nous définirons une variable dans l'application l'indiquant. Cela nous aide
            // savons que nous devons définir ultérieurement tous les cookies en file d'attente dans l'événement after.
            $app['auth.loaded'] = true;

            return new AuthManager($app);
        });

        $this->app->singleton('auth.driver', function ($app)
        {
            return $app['auth']->guard();
        });
    }

    /**
     * Enregistrez un résolveur pour l'utilisateur authentifié.
     *
     * @return void
     */
    protected function registerUserResolver()
    {
        $this->app->bind('Two\Auth\Contracts\UserInterface', function ($app)
        {
            $callback = $app['auth']->userResolver();

            return call_user_func($callback);
        });
    }

    /**
     * Enregistrez le service de portail d'accès.
     *
     * @return void
     */
    protected function registerAccessGate()
    {
        $this->app->singleton('Two\Auth\Contracts\Access\GateInterface', function ($app)
        {
            return new Gate($app, function() use ($app)
            {
                $callback = $app['auth']->userResolver();

                return call_user_func($callback);
            });
        });
    }

    /**
     * Enregistrez un résolveur pour l'utilisateur authentifié.
     *
     * @return void
     */
    protected function registerRequestRebindHandler()
    {
        $this->app->rebinding('request', function ($app, $request)
        {
            $request->setUserResolver(function ($guard = null) use ($app)
            {
                $callback = $app['auth']->userResolver();

                return call_user_func($callback, $guard);
            });
        });
    }

}
