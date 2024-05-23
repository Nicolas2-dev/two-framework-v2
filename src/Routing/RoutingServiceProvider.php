<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use Two\Routing\Router;
use Two\Routing\Redirector;
use Two\Routing\UrlGenerator;
use Two\Routing\Response\ResponseFactory;

use Two\Application\Providers\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRouter();

        $this->registerUrlGenerator();

        $this->registerRedirector();

        $this->registerResponseFactory();

        // Enregistrez les prestataires de services supplémentaires.
        $this->app->register('Two\Routing\Assets\AssetServiceProvider');
    }

    /**
     * Enregistrez l'instance de routeur.
     *
     * @return void
     */
    protected function registerRouter()
    {
        $this->app->singleton('router', function ($app)
        {
            return new Router($app['events'], $app);
        });
    }

    /**
     * Enregistrez le service générateur d'URL.
     *
     * @return void
     */
    protected function registerUrlGenerator()
    {
        $this->app->singleton('url', function ($app)
        {
            // Le générateur d'URL a besoin de la collection de routes qui existe sur le routeur.
            // Gardez à l'esprit qu'il s'agit d'un objet, nous passons donc par références ici
            // et toutes les routes enregistrées seront disponibles pour le générateur.
            $routes = $app['router']->getRoutes();

            $url = new UrlGenerator($routes, $app->rebinding('request', function ($app, $request)
            {
                $app['url']->setRequest($request);
            }));

            $url->setSessionResolver(function ()
            {
                return $this->app['session'];
            });

            return $url;
        });
    }

    /**
     * Enregistrez le service de redirection.
     *
     * @return void
     */
    protected function registerRedirector()
    {
        $this->app->singleton('redirect', function ($app)
        {
            $redirector = new Redirector($app['url']);

            // Si la session est définie sur l'instance d'application, nous l'injecterons dans
            // l'instance du redirecteur. Cela permet aux réponses de redirection d'autoriser
            // pour les méthodes "with" très pratiques qui clignotent dans la session.
            if (isset($app['session.store'])) {
                $redirector->setSession($app['session.store']);
            }

            return $redirector;
        });
    }

    /**
     * Enregistrez l’implémentation de la fabrique de réponses.
     *
     * @return void
     */
    protected function registerResponseFactory()
    {
        $this->app->singleton('response.factory', function ($app)
        {
            return new ResponseFactory();
        });
    }
}
