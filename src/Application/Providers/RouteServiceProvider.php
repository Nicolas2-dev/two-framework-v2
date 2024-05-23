<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Providers;

use Two\Routing\Router;

use Two\Application\Providers\ServiceProvider;


class RouteServiceProvider extends ServiceProvider
{
    
    /**
     * L'espace de noms du contrôleur pour l'application.
     *
     * @var string|null
     */
    protected $namespace;


    /**
     * Amorcez tous les services d'application.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadRoutes();
    }

    /**
     * Charger les routes de l'application.
     *
     * @return void
     */
    protected function loadRoutes()
    {
        if (method_exists($this, 'map')) {
            $router = $this->app['router'];

            call_user_func(array($this, 'map'), $router);
        }
    }

    /**
     * Chargez le fichier Routes standard pour l'application.
     *
     * @param  string  $path
     * @return mixed
     */
    protected function loadRoutesFrom($path)
    {
        $router = $this->app['router'];

        if (is_null($this->namespace)) {
            return require $path;
        }

        $router->group(array('namespace' => $this->namespace), function (Router $router) use ($path)
        {
            require $path;
        });
    }

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Transmettez des méthodes dynamiques à l'instance de routeur.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $router = $this->app['router'];

        return call_user_func_array(array($router, $method), $parameters);
    }
}
